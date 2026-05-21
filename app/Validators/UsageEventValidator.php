<?php

namespace App\Validators;

use App\Models\Account\Customer;
use App\Models\Management\Organization;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UsageEventValidator
{
    public function validateEvents(array $events, Organization $org): array
    {
        $validationErrors = [];
        $databaseValidationErrors = [];

        // First pass: Validate structure and data types
        foreach ($events as $index => $event) {
            try {
                $this->validateSingleEvent($event);
            } catch (ValidationException $e) {
                $validationErrors[$index] = [
                    'id' => $event['id'] ?? null,
                    'errors' => $e->errors()
                ];
            }
        }

        // If we have validation errors, no need to check database constraints
        if (!empty($validationErrors)) {
            return [
                'has_errors' => true,
                'validation_errors' => $validationErrors,
                'database_errors' => []
            ];
        }

        // Second pass: Validate database constraints
        $customerCache = [];
        foreach ($events as $index => $event) {
            try {
                $customerId = $event['customer'];

                // Only query the database once per unique customer
                if (!isset($customerCache[$customerId])) {
                    $customer = Customer::query()
                        ->where('reference_id', $customerId)
                        ->where('organization_id', $org->id)
                        ->first();

                    if (!$customer) {
                        throw new \Exception("Customer with reference ID '{$customerId}' not found");
                    }

                    $customerCache[$customerId] = $customer;
                }
            } catch (\Exception $e) {
                $databaseValidationErrors[$index] = [
                    'id' => $event['id'] ?? null,
                    'errors' => ['database' => [$e->getMessage()]]
                ];
            }
        }

        return [
            'has_errors' => !empty($databaseValidationErrors),
            'validation_errors' => $validationErrors,
            'database_errors' => $databaseValidationErrors
        ];
    }

    private function validateSingleEvent(array $event): array
    {
        $validator = Validator::make($event, [
            'id' => 'sometimes|string',
            'event' => 'required|string|max:255',
            'quantity' => 'sometimes|numeric|min:0',
            'user' => 'sometimes|string',
            'group' => 'sometimes|string',
            'customer' => 'required|string',
            'properties' => 'sometimes|array',
            'properties.quantity' => 'sometimes|numeric|min:0',
            'metadata' => 'sometimes|array',
            'metadata.*' => 'sometimes|string|numeric|boolean',
            'timestamp' => [
                'sometimes',
                'numeric',
                function ($attribute, $value, $fail) {
                    if ($value > time() + 300) {
                        $fail('The timestamp cannot be more than 5 minutes in the future.');
                    }
                },
            ]
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
