<?php

namespace Webkul\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Webkul\Core\Rules\AlphaNumericSpace;
use Webkul\Core\Rules\PhoneNumber;

class CartAddressRequest extends FormRequest
{
    /**
     * Rules.
     *
     * @var array
     */
    protected $rules = [];

    /**
     * Determine if the product is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        if ($this->has('billing')) {
            $this->mergeAddressRules('billing');
        }

        if (! $this->input('billing.use_for_shipping')) {
            $this->mergeAddressRules('shipping');
        }

        return $this->rules;
    }

    /**
     * Merge new address rules.
     *
     * @return void
     */
    private function mergeAddressRules(string $addressType)
    {
        $this->mergeWithRules([
            "{$addressType}.first_name" => ['required', 'string'],
            "{$addressType}.last_name"  => ['required', 'string'],
            "{$addressType}.email"      => ['required'],
            "{$addressType}.address"    => ['required', 'array', 'min:1'],
            "{$addressType}.city"       => ['required'],
            "{$addressType}.country"    => ['string'],
            "{$addressType}.state"      => ['string'],
            "{$addressType}.postcode"   => ['numeric'],
            "{$addressType}.phone"      => ['required', new PhoneNumber],
        ]);
    }

    /**
     * Merge additional rules.
     */
    private function mergeWithRules($rules): void
    {
        $this->rules = array_merge($this->rules, $rules);
    }
}
