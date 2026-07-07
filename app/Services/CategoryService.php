<?php
namespace App\Services;
use App\Models\Category;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CategoryService{

    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }


 public function getAllCategories($search = null, $perPage = 10){

      $query = Category::query();

      if($search)
      {
        $search = strtolower($search);
        $query->where(function ($query) use ($search) {
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
        });
      }

     return $query->withCount('products')->paginate($perPage)->withQueryString();
 }


 public function create(array $categoryData){

    $category = Category::create([
        'name' => $categoryData['name'],
        'description' => $categoryData['description']
    ]);

    $this->activityLogService->log(
        module: 'Category',
        action: 'Create',
        description: "Category created: {$category->name}",
        userId: auth()->id()
    );

    return $category;
 }



 public function getCategoryById($id)
 {

     $category = Category::findOrFail($id);

     return $category;

 }



 public function update($id, array $update)
 {

    $category = Category::findOrfail($id);
    $oldName = $category->name;

    $category->update([
       'name' => $update['name'],
       'description' => $update['description']
    ]);

    $this->activityLogService->log(
        module: 'Category',
        action: 'Update',
        description: "Category updated from '{$oldName}' to '{$category->name}'",
        userId: auth()->id()
    );

     return $category;

 }



 public function delete($id){

    $category = Category::findOrFail($id);
    $categoryName = $category->name;

    if ($category->products()->exists()) {
        throw new ConflictHttpException('Category cannot be deleted while products are assigned to it.');
    }

    $category->delete();

    $this->activityLogService->log(
        module: 'Category',
        action: 'Delete',
        description: "Category deleted: {$categoryName}",
        userId: auth()->id()
    );

   return true;

 }

}
