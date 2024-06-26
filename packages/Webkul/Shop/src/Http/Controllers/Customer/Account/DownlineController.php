<?php

namespace Webkul\Shop\Http\Controllers\Customer\Account;

use Webkul\Shop\Http\Controllers\Controller;
use Webkul\Shop\DataGrids\DownlineDataGrid;

class DownlineController extends Controller
{

    /**
     * Displays the listing resources if the customer having items in wishlist.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if (request()->ajax()) {
             return app(DownlineDataGrid::class)->toJson();
        }

        $downlines = auth()->guard('customer')->user()->downlines;
        //dd($downlines);

        return view('shop::customers.account.downlines.index')->with(compact('downlines'));
    }
}
