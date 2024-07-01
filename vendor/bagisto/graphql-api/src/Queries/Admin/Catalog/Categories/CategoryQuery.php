<?php

namespace Webkul\GraphQLAPI\Queries\Admin\Catalog\Categories;

use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Product\Repositories\ProductFlatRepository;
use Webkul\GraphQLAPI\Queries\BaseFilter;

class CategoryQuery extends BaseFilter
{
    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Category\Repositories\CategoryRepository  $categoryRepository
     * @param  \Webkul\Product\Repositories\ProductFlatRepository  $productFlatRepository
     * @return void
     */
    public function __construct(
        protected CategoryRepository $categoryRepository,
        protected ProductFlatRepository $productFlatRepository
    ) {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function getProductCount($rootValue, array $args, GraphQLContext $context)
    {
        $categoryId = isset($rootValue->id) ? $rootValue->id : core()->getCurrentChannel()->root_category_id;

        $queryBuilder = DB::table('product_categories as pc')
            ->select(DB::raw('COUNT(DISTINCT '.DB::getTablePrefix().'pc.product_id) as count'))
            ->leftJoin('product_flat as pf', 'pc.product_id', '=', 'pf.product_id')
            ->where('pf.channel', core()->getRequestedChannelCode())
            ->where('pf.locale', core()->getRequestedLocaleCode())
            ->where('pf.status', 1)
            ->where('pf.visible_individually', 1)
            ->where('pc.category_id', $categoryId);

        $result = $queryBuilder->first();

        return isset($result->count) ? $result->count : 0;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function getbreadcrumbs($rootValue, array $args, GraphQLContext $context)
    {
        $breadcrumbs = $categorySlug = [];

        if ($rootValue->url_path) {
            $categorySlug = explode("/", $rootValue->url_path);
        } else {
            $categorySlug[] = $rootValue->slug;
        }

        if (! empty($categorySlug)) {
            foreach ($categorySlug as $slug) {
                $category = $this->categoryRepository->findBySlugOrFail($slug);
                if ($category) {
                    array_push($breadcrumbs, [
                        'name'      => $category->name,
                        'slug'      => $category->slug,
                        'url_path'  => $category->url_path,
                    ]);
                }
            }
        }

        return $breadcrumbs;
    }

    /**
     * Get product maximum price based on category.
     *
     * @return \Illuminate\View\View
     */
    public function getCategoryProductMaxPrice($rootValue, array $args, GraphQLContext $context)
    {
        return core()->convertPrice($this->productFlatRepository->getCategoryProductMaximumPrice($rootValue));
    }
}
