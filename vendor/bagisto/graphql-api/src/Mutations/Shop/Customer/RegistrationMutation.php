<?php

namespace Webkul\GraphQLAPI\Mutations\Shop\Customer;

use JWTAuth;
use Exception;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Webkul\Customer\Repositories\CustomerRepository;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Webkul\Shop\Mail\Customer\RegistrationNotification;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\GraphQLAPI\Mail\SocialLoginPasswordResetEmail;
use Webkul\GraphQLAPI\Validators\Customer\CustomException;
use Webkul\Shop\Mail\Customer\EmailVerificationNotification;

class RegistrationMutation extends Controller
{
    /**
     * Contains current guard
     *
     * @var array
     */
    protected $guard;

    /**
     * Contains error codes
     *
     * @var array
     */
    protected $errorCode = [
        'validation.unique',
        'validation.required',
        'validation.same',
    ];

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Customer\Repositories\CustomerRepository  $customerRepository
     * @param  \Webkul\Customer\Repositories\CustomerGroupRepository  $customerGroupRepository
     * @return void
     */
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected CustomerGroupRepository $customerGroupRepository
    ) {
        $this->guard = 'api';

        auth()->setDefaultDriver($this->guard);

        $this->middleware('auth:'.$this->guard, ['except' => ['register']]);
    }

    /**
     * Method to store user's sign up form data to DB.
     *
     * @return \Illuminate\Http\Response
     */
    public function register($rootValue, array $args , GraphQLContext $context)
    {
        if (empty($args['input'])) {
            throw new CustomException(
                trans('bagisto_graphql::app.shop.response.error-invalid-parameter'),
                'Invalid request parameters.'
            );
        }

        $data = $args['input'];

        $validator = Validator::make($data, [
            'first_name'            => 'string|required',
            'last_name'             => 'string|required',
            'email'                 => 'email|required|unique:customers,email',
            'password'              => 'min:6|required',
            'password_confirmation' => 'required|required_with:password|same:password',
        ]);
        
        if ($validator->fails()) {
            $errorMessage = [];

            foreach ($validator->messages()->toArray() as $index => $message) {
                $error = is_array($message) ? $message[0] : $message;

                $errorMessage[] = in_array($error, $this->errorCode) ? trans('bagisto_graphql::app.'.$error, ['field' => $index]) : $error;
            }

            throw new CustomException(
                implode(", ", $errorMessage),
                'Invalid Register Details.'
            );
        }

        $verificationData['email'] = $data['email'];

        $verificationData['token'] = md5(uniqid(rand(), true));

        $data = array_merge($data, [
            'password'                  => bcrypt($data['password']),
            'api_token'                 => Str::random(80),
            'is_verified'               => (int) core()->getConfigData('customer.settings.email.verification') ? 0 : 1,
            'subscribed_to_news_letter' => ! empty($data['subscribed_to_news_letter']) ? 1 : 0,
            'customer_group_id'         => $this->customerGroupRepository->findOneByField('code', 'general')->id,
            'token'                     => $verificationData['token'],
        ]);

        Event::dispatch('customer.registration.before');

        $customer = $this->customerRepository->create($data);

        Event::dispatch('customer.registration.after', $customer);

        if (! $customer) {
            return [
                'status'  => false,
                'success' => trans('bagisto_graphql::app.shop.customer.signup-form.error-registration')
            ];
        }

        if (core()->getConfigData('customer.settings.email.verification')) {

            try {
                if (core()->getConfigData('emails.general.notifications.emails.general.notifications.verification')) {
                    Mail::queue(new EmailVerificationNotification($verificationData));
                }
            } catch (Exception $e) {}

            return [
                'status'  => true,
                'success' => trans('bagisto_graphql::app.shop.customer.signup-form.success-verify')
            ];
        }

        try {
            if (core()->getConfigData('emails.general.notifications.emails.general.notifications.registration')) {
                Mail::queue(new RegistrationNotification($data, 'customer'));
            }

            if (core()->getConfigData('emails.general.notifications.emails.general.notifications.customer-registration-confirmation-mail-to-admin')) {
                Mail::queue(new RegistrationNotification(request()->all(), 'admin'));
            }
        } catch (Exception $e) {}

        $remember = !empty($data['remember']) ? 1 : 0;

        if (! $jwtToken = JWTAuth::attempt([
                'email'    => $data['email'],
                'password' => $data['password_confirmation'],
            ], $remember)
        ) {
            throw new CustomException(
                trans('bagisto_graphql::app.shop.customer.signup-form.invalid-creds'),
                'Invalid Email and Password.'
            );
        }

        $loginCustomer = bagisto_graphql()->guard($this->guard)->user();

        if (
            ! $loginCustomer->status
            || ! $loginCustomer->is_verified
        ) {
            bagisto_graphql()->guard($this->guard)->logout();

            throw new CustomException(
                trans('bagisto_graphql::app.shop.customer.login-form.not-activated'),
                trans('bagisto_graphql::app.shop.customer.login-form.not-activated')
            );
        }

        return [
            'status'       => true,
            'success'      => trans('bagisto_graphql::app.shop.customer.success-login'),
            'access_token' => 'Bearer '.$jwtToken,
            'token_type'   => 'Bearer',
            'expires_in'   => bagisto_graphql()->guard($this->guard)->factory()->getTTL() * 60,
            'customer'     => $this->customerRepository->find($loginCustomer->id),
        ];
    }

    /**
     * Method to store user's sign up form data to DB.
     *
     * @return \Illuminate\Http\Response
     */
    public function socialSignIn($rootValue, array $args , GraphQLContext $context)
    {
        $data = $args;

        $validator = Validator::make($data, [
            'first_name'  => 'string|required',
            'last_name'   => 'string|required',
            'email'       => 'email|required',
            'signup_type' => 'string|required',
        ]);

        $errorMessage = [];

        if ($validator->fails()) {
            foreach ($validator->messages()->toArray() as $index => $message) {
                $error = is_array($message) ? $message[0] : $message;

                $errorMessage[] = in_array($error, $this->errorCode) ? trans('bagisto_graphql::app.'.$error, ['field' => $index]) : $error;
            }

            throw new CustomException(
                implode(", ", $errorMessage),
                'Invalid Register Details.'
            );
        }

        if ($data['signup_type'] == 'truecaller') {
            if (empty($data['phone'])) {
                throw new CustomException(
                    trans('bagisto_graphql::app.shop.customer.signup-form.validation.required', ['field' => 'phone number']),
                    'Invalid request parameters.'
                );
            }

            $customer = $this->customerRepository->findOneByField('phone', $data['phone']);

            if (
                $customer
                && $customer->email != $data['email']
            ) {
                throw new CustomException(
                    trans('bagisto_graphql::app.shop.customer.signup-form.warning-num-already-used', ['phone' => $data['phone']]),
                    'Invalid request parameters.'
                );
            }
        } else {
            $customer = $this->customerRepository->findOneByField('email', $data['email']);
        }

        if ($customer) {

            if (
                ! $customer->status
                || ! $customer->is_verified
            ) {
                throw new CustomException(
                    trans('bagisto_graphql::app.shop.customer.login-form.not-activated'),
                    'Account Not Activated.'
                );
            }

            return $this->loginCustomer($customer, $data);
        }

        $token = md5(uniqid(rand(), true));

        $data['password'] = $data['password_confirmation'] = rand(1,999999);

        $data = array_merge($data, [
            'password'          => bcrypt($data['password']),
            'api_token'         => Str::random(80),
            'is_verified'       => core()->getConfigData('customer.settings.email.verification') ? 0 : 1,
            'customer_group_id' => $this->customerGroupRepository->findOneWhere(['code' => 'general'])->id,
            'token'             => $token,
        ]);

        $verificationData['email'] = $data['email'];
        $verificationData['token'] = $token;

        Event::dispatch('customer.registration.before');

        $customer = $this->customerRepository->create($data);

        if (! $customer) {
            return [
                'status'  => false,
                'success' => trans('bagisto_graphql::app.shop.customer.signup-form.error-registration')
            ];
        }

        Event::dispatch('customer.registration.after', $customer);

        try {
            if (core()->getConfigData('emails.general.notifications.emails.general.notifications.registration')) {

                $data['is_social_login'] = true;

                Mail::queue(new RegistrationNotification($data, 'customer'));
            }

            if (core()->getConfigData('emails.general.notifications.emails.general.notifications.customer-registration-confirmation-mail-to-admin')) {
                Mail::queue(new RegistrationNotification($data, 'admin'));
            }
        } catch (Exception $e) {}

        $data['password'] = $data['password_confirmation'];

        return $this->loginCustomer($customer, $data);
    }

    /**
     * Login the customer using socialSignIn API.
     *
     * @param  \Webkul\Customer\Repositories\CustomerRepository  $customer
     * @param  array  $data
     * @return \Illuminate\Http\Response
     */
    public function loginCustomer($customer, $data)
    {
        $jwtToken = null;

        if (! isset($data['password'])) {
            $data['password'] = $password = rand(1, 999999);

            $customer->password = bcrypt($password);
            $customer->save();

            try {
                Mail::queue(new SocialLoginPasswordResetEmail($data));
            } catch(Exception $e) {}

        }

        $remember = isset($data['remember']) ? $data['remember'] : 0;

        if (! $jwtToken = JWTAuth::attempt([
            'email'    => $customer->email,
            'password' => $data['password'],
        ], $remember)) {
            throw new CustomException(
                trans('bagisto_graphql::app.shop.customer.login-form.invalid-creds'),
                'Invalid Email and Password.'
            );
        }

        $customer = bagisto_graphql()->guard($this->guard)->user();

        /**
         * Event passed to prepare cart after login.
         */
        Event::dispatch('customer.after.login', $customer->email);

        return [
            'status'       => true,
            'success'      => trans('bagisto_graphql::app.shop.customer.success-login'),
            'access_token' => 'Bearer '.$jwtToken,
            'token_type'   => 'Bearer',
            'expires_in'   => bagisto_graphql()->guard($this->guard)->factory()->getTTL() * 60,
            'customer'     => $this->customerRepository->find($customer->id),
        ];
    }
}
