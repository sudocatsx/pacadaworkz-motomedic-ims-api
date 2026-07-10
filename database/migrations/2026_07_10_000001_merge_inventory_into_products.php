<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateProductIds = DB::table('inventory')
            ->whereNull('deleted_at')
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('product_id');

        if ($duplicateProductIds->isNotEmpty()) {
            throw new \RuntimeException(
                'Duplicate active inventory rows must be resolved for product IDs: '.$duplicateProductIds->join(', ')
            );
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('image_original_name')->nullable()->after('image_url');
            $table->string('image_mime_type', 100)->nullable()->after('image_original_name');
            $table->unsignedBigInteger('image_size_bytes')->nullable()->after('image_mime_type');
            $table->string('image_source', 20)->nullable()->after('image_size_bytes');
        });

        DB::table('products')
            ->where('image_url', '/storage/dummy_image.jpg')
            ->update(['image_url' => null]);

        Schema::table('inventory', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id')->constrained('products');
            $table->unsignedInteger('previous_quantity')->nullable()->after('reason');
            $table->unsignedInteger('counted_quantity')->nullable()->after('previous_quantity');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_adjustments DROP CONSTRAINT IF EXISTS stock_adjustments_reason_check');
            DB::statement('ALTER TABLE stock_adjustments ALTER COLUMN reason TYPE VARCHAR(50)');
            DB::statement('CREATE UNIQUE INDEX inventory_product_id_active_unique ON inventory (product_id) WHERE deleted_at IS NULL');
            DB::statement('ALTER TABLE inventory ADD CONSTRAINT inventory_quantity_nonnegative CHECK (quantity >= 0)');
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_reference_type_check');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reference_type_check CHECK (reference_type IN ('purchase', 'sale', 'adjustment', 'return', 'opening'))");
        } else {
            Schema::table('stock_adjustments', function (Blueprint $table) {
                $table->string('reason', 50)->change();
            });
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->enum('reference_type', ['purchase', 'sale', 'adjustment', 'return', 'opening'])->change();
            });
            DB::statement('CREATE UNIQUE INDEX inventory_product_id_active_unique ON inventory (product_id) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_product_id_active_unique');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory DROP CONSTRAINT IF EXISTS inventory_quantity_nonnegative');
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_reference_type_check');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reference_type_check CHECK (reference_type IN ('purchase', 'sale', 'adjustment', 'return'))");
        }

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['previous_quantity', 'counted_quantity']);
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'image_original_name',
                'image_mime_type',
                'image_size_bytes',
                'image_source',
            ]);
        });
    }
};
