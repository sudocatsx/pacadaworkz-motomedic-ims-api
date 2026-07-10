<?php

namespace App\Http\Controllers\API;

use App\Services\AttributeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\AttributeResource;
use App\Http\Requests\Attribute\AttributeRequest;
use App\Http\Resources\AttributesValueResource;
use App\Http\Requests\Attribute\AttributesValueRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AttributeController
{


    protected $attributeService;
    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
    }

    //show all attributes
    public function index(Request $request)
    {


        try {
            $search = $request->query('search', null);
            $perPage = $request->query('per_page', 10);
            $result = $this->attributeService->getAllAttributes($search, $perPage);
            return response()->json([
                'success' => true,
                'data' => AttributeResource::collection($result),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'last_page' => $result->lastPage(),
                    'total_pages' => $result->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //show attribute by id
    public function show($id)
    {

        try {
            $result = $this->attributeService->getAttributeById($id);
            return response()->json([
                'success' => true,
                'data' => new AttributeResource($result)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found'
            ], 404);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }




    // create new Attribute
    public function store(AttributeRequest $request)
    {

        try {
            $result = $this->attributeService->create($request->validated());

            return response()->json([
                'success' => true,
                'data' => new AttributeResource($result)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    // update attribute
    public function update(AttributeRequest $request, $id)
    {

        try {
            $result = $this->attributeService->update($request->validated(), $id);

            return response()->json([
                'success' => true,
                'data' => new AttributeResource($result)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }



    //destroy attribute
    public function destroy($id)
    {
        try {

            $result = $this->attributeService->delete($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Attribute deleted successfully'
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }



    //store AttributsValue
    public function storeAttributesValue(AttributesValueRequest $request, $id)
    {
        try {
            $result = $this->attributeService->createAttributesValue($request->validated(), $id);

            return response()->json([
                'success' => true,
                'data' => new AttributesValueResource($result)
            ], 201);
        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Attribute not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //update AttributesValue
    public function updateAttributesValue(AttributesValueRequest $request, $valueId)
    {
        try {
            $result = $this->attributeService->updateAttributesValue($request->validated(), $valueId);

            return response()->json([
                'success' => true,
                'data' => new AttributesValueResource($result)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute value not found'
            ], 404);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //delete AttributesValue
    public function destroyAttributesValue($valueId)
    {
        try {
            $this->attributeService->deleteAttributesValue($valueId);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Attribute value deleted successfully'
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute value not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
