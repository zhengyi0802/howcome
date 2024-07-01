<?php

namespace Webkul\GraphQLAPI\Models\Catalog;

use Illuminate\Support\Facades\DB;
use Webkul\Category\Models\Category as BaseModel;

class Category extends BaseModel
{
    //Make it available in the json response
    protected $appends = ['count'];

    protected $with = ['translations'];

    //implement the attribute
    public function getCountAttribute()
    {
        $data = DB::table('product_categories')->where("category_id",$this->id)->get();

        return count($data);
    }
}
