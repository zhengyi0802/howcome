<?php

namespace Webkul\GraphQLAPI\Mutations\Shop\Customer;

use Exception;
use Webkul\Checkout\Facades\Cart;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Customer\Repositories\WishlistRepository;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Webkul\GraphQLAPI\Validators\Customer\CustomException;

class WishlistMutation extends Controller
{
    /**
     * Contains current guard
     *
     * @var array
     */
    protected $guard;

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Customer\Repositories\WishlistRepository  $wishlistRepository
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @return void
     */
    public function __construct(
       protected WishlistRepository $wishlistRepository,
       protected ProductRepository $productRepository
    ) {
        $this->guard = 'api';

        auth()->setDefaultDriver($this->guard);

        $this->middleware('auth:'.$this->guard);
    }

    /**
     * Returns loggedin customer's wishlists data.
     *
     * @return \Illuminate\Http\Response
     */
    public function wishlists($rootValue, array $args , GraphQLContext $context)
    {
        if (! bagisto_graphql()->guard($this->guard)->check() ) {
            throw new CustomException(
                trans('bagisto_graphql::app.shop.customer.no-login-customer'),
                trans('bagisto_graphql::app.shop.customer.no-login-customer')
            );
        }

        try {
            $params = isset($args['input']) ? $args['input'] : (isset($args['id']) ? $args : []);

            $currentPage = isset($params['page']) ? $params['page'] : 1;

            Paginator::currentPageResolver(function () use ($currentPage) {
                return $currentPage;
            });

            $wishlists = app(WishlistRepository::class)->scopeQuery(function ($query) use ($params) {
                $channel = isset($params['channel']) ?: (core()->getCurrentChannelCode() ?: core()->getDefaultChannelCode());

                $locale = isset($params['locale']) ?: app()->getLocale();

                $customer = bagisto_graphql()->guard($this->guard)->user();

                $qb = $query->distinct()
                    ->addSelect('wishlist.*')
                    ->addSelect('product_flat.name as product_name')
                    ->leftJoin('product_flat', 'wishlist.product_id', '=', 'product_flat.product_id')
                    ->where('product_flat.channel', $channel)
                    ->where('product_flat.locale', $locale)
                    ->where('wishlist.customer_id', $customer->id);

                if (isset($params['id']) && $params['id']) {
                    $qb->where('wishlist.id', $params['id']);
                }

                if (isset($params['product_name']) && $params['product_name']) {
                    $qb->where('product_flat.name', 'like', '%'.urldecode($params['product_name']).'%');
                }

                if (isset($params['product_id']) && $params['product_id']) {
                    $qb->where('wishlist.product_id', $params['product_id']);
                }

                if (isset($params['channel_id']) && $params['channel_id']) {
                    $qb->where('wishlist.channel_id', $params['channel_id']);
                }

                return $qb;
            });

            if (isset($args['id'])) {
                $wishlists = $wishlists->first();
            } else {
                $wishlists = $wishlists->paginate(isset($params['limit']) ?? 10);
            }

            if ($wishlists) {
                return $wishlists;
            } else {
                throw new CustomException(
                    trans('bagisto_graphql::app.shop.customer.account.not-found', ['name' => 'Wishlist Item']),
                    trans('bagisto_graphql::app.shop.customer.account.not-found', ['name' => 'Wishlist Item'])
                );
            }
        } catch (Exception $e) {
            throw new CustomException(
                $e->getMessage(),
                $e->getMessage()
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store($rootValue, array $args, GraphQLContext $context)
    {
        if (empty($args['input'])) {
            throw new CustomException(
                trans('bagisto_graphql::app.admin.response.error-invalid-parameter'),
                trans('bagisto_graphql::app.admin.response.error-invalid-parameter')
            );
        }

        if (! bagisto_graphql()->guard($this->guard)->check() ) {
            throw new CustomException(
                trans('bagisto_graphql::app.shop.customer.no-login-customer'),
                trans('bagisto_graphql::app.shop.customer.no-login-customer')
            );
        }

        $data = $args['input'];

        $validator = Validator::make($data, [
            'product_id' => 'required',
        ]);

        if ($validator->fails()) {
            throw new CustomException(
                $validator->messages(),
                $validator->messages()
            );
        }

        try {
            $data['channel_id'] = core()->getCurrentChannel()->id;
            if (bagisto_graphql()->guard($this->guard)->check()) {
                $data['customer_id'] = bagisto_graphql()->guard($this->guard)->user()->id;
            }

            $product = $this->productRepository->findOrFail($data['product_id']);

            if (! $product->status) {
                throw new CustomException(
                    trans('bagisto_graphql::app.shop.response.invalid-product'),
                    trans('bagisto_graphql::app.shop.response.invalid-product')
                );
            }

            if ($product->parent_id != null) {
                $product = $this->productRepository->findOrFail($product->parent_id);
                $data['product_id'] = $product->id;
            }

            $wishlist = $this->wishlistRepository->findOneWhere($data);

            if ($wishlist) {
                unset($data['product_id']);

                return [
                    'success'  => trans('bagisto_graphql::app.shop.customer.account.wishlist.already-exist'),
                    'wishlist' => $this->wishlistRepository->findWhere($data)
                ];
            }

            $wishlist = $this->wishlistRepository->create($data);

            unset($data['product_id']);

            return [
                'success'  => trans('bagisto_graphql::app.shop.customer.account.wishlist.success'),
                'wishlist' => $this->wishlistRepository->findWhere($data)
            ];
        } catch (Exception $e) {
            throw new CustomException(
                $e->getMessage(),
                $e->getMessage()
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  array  $args
     * @return \Illuminate\Http\Response
     */
    public function delete($rootValue, array $args, GraphQLContext $context)
    {
        if (empty($args['input'])) {
            throw new CustomException(
                trans('bagisto_graphql::app.shop.response.error-invalid-parameter'),
                trans('bagisto_graphql::app.shop.response.error-invalid-parameter')
            );
        }

        if (! bagisto_graphql()->guard($this->guard)->check() ) {
            throw new CustomException(
                trans('bagisto_graphql::app.shop.customer.no-login-customer'),
                trans('bagisto_graphql::app.shop.customer.no-login-customer')
            );
        }

        $data = $args['input'];

        $validator = Validator::make($data, [
            'product_id' => 'required',
        ]);

        if ($validator->fails()) {
            throw new CustomException(
                $validator->messages(),
                $validator->messages()
            );
        }

        try {
            $data['channel_id'] = core()->getCurrentChannel()->id;

            if (bagisto_graphql()->guard($this->guard)->check()) {
                $data['customer_id'] = bagisto_graphql()->guard($this->guard)->user()->id;
            }

            $product = $this->productRepository->findOrFail($data['product_id']);

            if ($product->parent_id != null ) {
                $product = $this->productRepository->findOrFail($product->parent_id);
                $data['product_id'] = $product->id;
            }

            $wishlist = $this->wishlistRepository->findOneWhere($data);

            if ($wishlist) {

                $this->wishlistRepository->delete($wishlist->id);

                return [
                    'status'   => true,
                    'success'  => trans('bagisto_graphql::app.shop.customer.account.wishlist.removed'),
                    'wishlist' => $this->wishlistRepository->findWhere($data)
                ];
            }

            return [
                'status'   => false,
                'success'  => trans('bagisto_graphql::app.shop.customer.account.not-found', ['name'    => 'Wishlist Product']),
                'wishlist' => $this->wishlistRepository->findWhere($data)
            ];
        } catch (Exception $e) {
            throw new CustomException(
                $e->getMessage(),
                $e->getMessage()
            );
        }
    }

    /**
     * Move wishlist item to cart
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function move($rootValue, array $args, GraphQLContext $context)
    {
        if (empty($args['id'])) {
            throw new CustomException(
                trans('bagisto_graphql::app.admin.response.error-invalid-parameter'),
                trans('bagisto_graphql::app.admin.response.error-invalid-parameter')
            );
        }

        if (! bagisto_graphql()->guard($this->guard)->check() ) {
            throw new CustomException(
                trans('bagisto_graphql::app.shop.customer.no-login-customer'),
                trans('bagisto_graphql::app.shop.customer.no-login-customer')
            );
        }

        $id = $args['id'];

        try {
            $customer = bagisto_graphql()->guard($this->guard)->user();

            $wishlist = $this->wishlistRepository->findOneWhere([
                'id'          => $id,
                'customer_id' => $customer->id,
            ]);

            if (! empty($wishlist->id)) {
                request()->merge([
                    'quantity' => $args['quantity'],
                ]);

                $result = Cart::moveToCart($wishlist);

                if ($result ) {
                    return [
                        'status'   => true,
                        'success'  => trans('bagisto_graphql::app.shop.customer.account.wishlist.moved-success'),
                        'wishlist' => $this->wishlistRepository->findWhere(['customer_id' => $customer->id])
                    ];
                } else {
                    return [
                        'status'  => false,
                        'success' => trans('bagisto_graphql::app.shop.customer.account.wishlist.error-move-to-cart'),
                    ];
                }
            } else {
                return [
                    'status'  => false,
                    'success' => trans('bagisto_graphql::app.shop.customer.account.not-found', ['name'    => 'Wishlist Product']),
                ];
            }
        } catch (Exception $e) {
            throw new CustomException(
                $e->getMessage(),
                $e->getMessage()
            );
        }
    }

    /**
     * Remove all the wishlist entries of customer.
     *
     * @param  array  $args
     * @return \Illuminate\Http\Response
     */
    public function deleteAll($rootValue, array $args, GraphQLContext $context)
    {
        if (! bagisto_graphql()->guard($this->guard)->check() ) {
            throw new CustomException(
                trans('bagisto_graphql::app.shop.customer.no-login-customer'),
                trans('bagisto_graphql::app.shop.customer.no-login-customer')
            );
        }

        try {
            $wishlistItems = bagisto_graphql()->guard($this->guard)->user()->wishlist_items;

            if (! count($wishlistItems)) {

                return [
                    'status'  => false,
                    'success' => trans('bagisto_graphql::app.shop.customer.account.wishlist.no-item-found'),
                ];
            }

            foreach ($wishlistItems as $wishlistItem) {
                $this->wishlistRepository->delete($wishlistItem->id);
            }

            return [
                'status'  => true,
                'success' => trans('bagisto_graphql::app.shop.customer.account.wishlist.remove-all-success'),
            ];
        } catch (Exception $e) {
            throw new CustomException(
                $e->getMessage(),
                $e->getMessage()
            );
        }
    }

    /**
     * Share customer's wishlist.
     *
     * @param  array  $args
     * @return \Illuminate\Http\Response
     */
    public function share($rootValue, array $args, GraphQLContext $context)
    {
        try {
            $customer = bagisto_graphql()->guard($this->guard)->user();

            $updateCounts = $customer->wishlist_items()->update(['shared' => $args['shared']]);

            if (
                $updateCounts
                && $updateCounts > 0
            ) {
                return [
                    'isWishlistShared'   => $customer->isWishlistShared(),
                    'wishlistSharedLink' => $customer->getWishlistSharedLink()
                ];
            }
        } catch (Exception $e) {
            throw new CustomException(
                $e->getMessage(),
                $e->getMessage()
            );
        }
    }
}
