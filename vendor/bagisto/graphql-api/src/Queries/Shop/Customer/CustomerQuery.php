<?php

namespace Webkul\GraphQLAPI\Queries\Shop\Customer;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\GraphQLAPI\Queries\BaseFilter;

class CustomerQuery extends BaseFilter
{
    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Customer\Repositories\CustomerRepository $customerRepository
     * @param  \Webkul\Sales\Repositories\InvoiceRepository $invoiceRepository
    * @return void
     */
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected InvoiceRepository $invoiceRepository
    ) {
    }

    public function getTransactions($rootValue, array $args, GraphQLContext $context){
        return $this->invoiceRepository->whereHas('order',function($q) use ($args) {
            $q->where('customer_id', $args['customer_id']);
        })->get();
    }
}



