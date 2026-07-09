<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SpreadsheetService
{
    public function createXlsx(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary spreadsheet file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open temporary spreadsheet archive.');
        }

        $sheetNames = array_keys($sheets);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($sheetNames)));
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetNames));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml(count($sheetNames)));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        $index = 1;
        foreach ($sheets as $rows) {
            $zip->addFromString(
                'xl/worksheets/sheet' . $index . '.xml',
                $this->worksheetXml($rows)
            );
            $index++;
        }

        $zip->close();

        return $path;
    }

    public function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open XLSX file.');
        }

        $workbook = $this->loadXml($zip, 'xl/workbook.xml');
        $relationships = $this->loadXml($zip, 'xl/_rels/workbook.xml.rels');
        $sharedStrings = $this->readSharedStrings($zip);
        $relationshipTargets = [];

        foreach ($relationships->Relationship as $relationship) {
            $relationshipTargets[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        $sheets = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipId = (string) $attributes['id'];
            $target = $relationshipTargets[$relationshipId] ?? null;

            if (!$target) {
                continue;
            }

            $sheetPath = str_starts_with($target, 'worksheets/')
                ? 'xl/' . $target
                : 'xl/worksheets/' . basename($target);

            $sheets[(string) $sheet['name']] = $this->readWorksheet($zip, $sheetPath, $sharedStrings);
        }

        $zip->close();

        return $sheets;
    }

    public function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function rowsToCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private function readWorksheet(ZipArchive $zip, string $path, array $sharedStrings): array
    {
        $xml = $this->loadXml($zip, $path);
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $rowValues = [];
            $maxIndex = -1;

            foreach ($row->c as $cell) {
                $cellRef = (string) $cell['r'];
                $index = $this->columnIndexFromCellRef($cellRef);
                $rowValues[$index] = $this->cellValue($cell, $sharedStrings);
                $maxIndex = max($maxIndex, $index);
            }

            $ordered = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $ordered[] = $rowValues[$i] ?? '';
            }

            $rows[] = $ordered;
        }

        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = $this->loadXml($zip, 'xl/sharedStrings.xml');
        $strings = [];

        foreach ($xml->si as $item) {
            $strings[] = $this->collectText($item);
        }

        return $strings;
    }

    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            return $sharedStrings[(int) $cell->v] ?? '';
        }

        if ($type === 'inlineStr') {
            return $this->collectText($cell->is);
        }

        return isset($cell->v) ? (string) $cell->v : '';
    }

    private function collectText(\SimpleXMLElement $node): string
    {
        $text = '';

        if (isset($node->t)) {
            $text .= (string) $node->t;
        }

        foreach ($node->r as $run) {
            if (isset($run->t)) {
                $text .= (string) $run->t;
            }
        }

        return $text;
    }

    private function loadXml(ZipArchive $zip, string $path): \SimpleXMLElement
    {
        $contents = $zip->getFromName($path);
        if ($contents === false) {
            throw new RuntimeException("Missing spreadsheet part: {$path}");
        }

        $xml = simplexml_load_string($contents);
        if ($xml === false) {
            throw new RuntimeException("Invalid spreadsheet XML: {$path}");
        }

        return $xml;
    }

    private function columnIndexFromCellRef(string $cellRef): int
    {
        preg_match('/^[A-Z]+/', strtoupper($cellRef), $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        foreach (array_values($rows) as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xml .= '<row r="' . $rowNumber . '">';

            foreach (array_values($row) as $columnIndex => $value) {
                $cellRef = $this->columnName($columnIndex) . $rowNumber;
                $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' .
                    htmlspecialchars((string) $value, ENT_XML1) .
                    '</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private function columnName(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $name = chr(65 + $remainder) . $name;
            $index = intdiv($index - 1, 26);
        }

        return $name;
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            $overrides .
            '</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    private function workbookXml(array $sheetNames): string
    {
        $sheets = '';
        foreach ($sheetNames as $index => $name) {
            $sheetId = $index + 1;
            $safeName = substr(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name), 0, 31);
            $sheets .= '<sheet name="' . htmlspecialchars($safeName, ENT_XML1) . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>' . $sheets . '</sheets>' .
            '</workbook>';
    }

    private function workbookRelationshipsXml(int $sheetCount): string
    {
        $relationships = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $relationships .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            $relationships .
            '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>' .
            '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' .
            '<borders count="1"><border/></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>' .
            '</styleSheet>';
    }
}
