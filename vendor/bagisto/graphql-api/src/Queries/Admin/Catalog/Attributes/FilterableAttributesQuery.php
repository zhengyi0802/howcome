<?php

namespace Webkul\GraphQLAPI\Queries\Admin\Catalog\Attributes;

use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\GraphQLAPI\Queries\BaseFilter;

class FilterableAttributesQuery extends BaseFilter
{
    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Attribute\Repositories\AttributeRepository  $attributeRepository
     * @return void
     */
    public function __construct(protected AttributeRepository $attributeRepository)
    {
        $this->_config = request('_config');
    }

    /**
     * filter the data .
     *
     * @param  object  $query
     * @param  array $input
     * @return \Illuminate\Http\Response
     */
    public function __invoke($query, $input)
    {
        $arguments = $this->getFilterParams($input);

        return $query->where($arguments);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function getFilterableAttributes()
    {
        return $this->attributeRepository->findByField('is_filterable', 1);
    }
}
