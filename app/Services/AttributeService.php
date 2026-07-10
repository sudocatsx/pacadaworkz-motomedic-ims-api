<?php

namespace App\Services;
use App\Models\Attribute;
use App\Models\AttributesValue;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AttributeService{

    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }
 

    //get all attributes
     public function getAllAttributes($search = null, $perPage = 10){
    
        $query = Attribute::with('attribute_values.attribute');
       
        if($search) {
            $search = strtolower($search);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
        }



        return $query->paginate($perPage)->withQueryString();
     }



     //get attributes by id
    public function getAttributeById($id){
            
        return Attribute::with('attribute_values.attribute')->findOrFail($id);
    }

   
 //crate new Attribute
        public function create(array $data){
    
            $attribute = Attribute::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
    
            $this->activityLogService->log(
                'Attribute', // module
                'created',   // action
                'Attribute created: ' . $attribute->name,
                Auth::id()
            );
    
            return $attribute;
        }


//update attribute
  public function update(array $data,$id){
      $attribute = Attribute::findOrFail($id);

      $attribute->update([
          'name' => $data['name'],
          'description' => $data['description'] ?? null,
      ]);

                  $this->activityLogService->log(
                      'Attribute', // module
                      'updated',   // action
                      'Attribute updated: ' . $attribute->name,
                      Auth::id()
                  );
      return $attribute;
  } 
  

  //delete attribute
   public function delete($id){
        $attribute = Attribute::findOrFail($id);
        $attributeName = $attribute->name; // Capture name before deletion

        if ($attribute->attribute_values()->exists()) {
            throw new ConflictHttpException('Attribute cannot be deleted while values are assigned to it.');
        }

        $attribute->delete();

                    $this->activityLogService->log(
                        'Attribute', // module
                        'deleted',   // action
                        'Attribute deleted: ' . $attributeName,
                        Auth::id()
                    );   }


   //create value to the specific attribute
   public function createAttributesValue(array $data,$id){
        $attribute_value = AttributesValue::create([
        'attribute_id' => $id,
        'value' => $data['value'],
      ]);

      $attribute = Attribute::findOrFail($id);

      $attribute_name = $attribute->name;
      $this->activityLogService->log(
          'Attribute Value', // module
          'created',         // action
          'Attribute Value created: ' . $attribute_value->value . ' for Attribute : ' . $attribute_name,
          Auth::id()
      );

      return $attribute_value;
   }

   //update value of the specific attribute
   public function updateAttributesValue(array $data, $valueId)
   {
       $attribute_value = AttributesValue::findOrFail($valueId);
       $oldValue = $attribute_value->value;

       $attribute_value->update([
           'value' => $data['value']
       ]);

       $attribute = $attribute_value->attribute;
       $this->activityLogService->log(
           'Attribute Value',
           'updated',
           'Attribute Value updated from ' . $oldValue . ' to ' . $attribute_value->value . ' for Attribute: ' . $attribute->name,
           Auth::id()
       );

       return $attribute_value;
   }

   //delete value of the specific attribute
   public function deleteAttributesValue($valueId)
   {
       $attribute_value = AttributesValue::findOrFail($valueId);
       $valueText = $attribute_value->value;
       $attributeName = $attribute_value->attribute->name;

       if ($attribute_value->products()->exists()) {
           throw new ConflictHttpException('Attribute value cannot be deleted while products use it.');
       }

       $attribute_value->delete();

       $this->activityLogService->log(
           'Attribute Value',
           'deleted',
           'Attribute Value deleted: ' . $valueText . ' from Attribute: ' . $attributeName,
           Auth::id()
       );

       return true;
   }

}
