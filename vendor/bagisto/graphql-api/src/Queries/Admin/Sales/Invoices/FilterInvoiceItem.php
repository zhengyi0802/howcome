<?php

namespace Webkul\GraphQLAPI\Queries\Admin\Sales\Invoices;

use Webkul\GraphQLAPI\Queries\BaseFilter;

class FilterInvoiceItem extends BaseFilter
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
