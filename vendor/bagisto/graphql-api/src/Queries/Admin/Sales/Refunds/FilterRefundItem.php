<?php

namespace Webkul\GraphQLAPI\Queries\Admin\Sales\Refunds;

use Webkul\GraphQLAPI\Queries\BaseFilter;

class FilterRefundItem extends BaseFilter
{
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
}
