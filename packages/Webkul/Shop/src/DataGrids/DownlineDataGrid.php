<?php

namespace Webkul\Shop\DataGrids;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\OrderRepository;

class DownlineDataGrid extends DataGrid
{
    /**
     * Index.
     *
     * @var string
     */
    protected $primaryColumn = 'customer_id';

    /**
     * Prepare query builder.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function prepareQueryBuilder()
    {
        $tablePrefix = DB::getTablePrefix();

        $queryBuilder = DB::table('customers')
            ->addSelect(
                'customers.id as customer_id',
                'customers.email',
                'customers.phone',
                'customers.gender',
            )
            ->addSelect(
                DB::raw('CONCAT('.$tablePrefix.'customers.last_name, '.$tablePrefix.'customers.first_name) as full_name')
            )
            ->where('upper_id', auth()->guard('customer')->user()->id);

        return $queryBuilder;
    }

    /**
     * Add columns.
     *
     * @return void
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index'      => 'customer_id',
            'label'      => trans('shop::app.customers.account.downlines.id'),
            'type'       => 'integer',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'full_name',
            'label'      => trans('shop::app.customers.account.downlines.full_name'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'email',
            'label'      => trans('shop::app.customers.account.downlines.email'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'phone',
            'label'      => trans('shop::app.customers.account.downlines.phone'),
            'type'       => 'integer',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'gender',
            'label'      => trans('shop::app.customers.account.downlines.gender'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
        ]);

    }

    /**
     * Prepare actions.
     *
     * @return void
     */
    public function prepareActions()
    {
    }
}
