<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CustomerStatus;
use App\Filters\CustomerFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ChangeCustomerStatusRequest;
use App\Http\Requests\Customer\ListCustomersRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(ListCustomersRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $page = (new CustomerFilter($request->validated()))
            ->apply(Customer::query()->withCount('orders'))
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page, CustomerResource::class);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = $this->customers->create($request->validated(), $request->user());

        return ApiResponse::created(new CustomerResource($customer->fresh()), 'Customer created successfully');
    }

    public function show(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        return ApiResponse::success(
            (new CustomerResource($customer->loadCount('orders')))->withStats($this->customers->stats($customer)),
        );
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        $customer = $this->customers->update($customer, $request->validated());

        return ApiResponse::success(new CustomerResource($customer->loadCount('orders')), 'Customer updated successfully');
    }

    public function updateStatus(ChangeCustomerStatusRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        $customer = $this->customers->changeStatus($customer, CustomerStatus::from($request->validated('status')));

        return ApiResponse::success(new CustomerResource($customer->loadCount('orders')), 'Customer status updated successfully');
    }

    public function destroy(Customer $customer): Response
    {
        Gate::authorize('delete', $customer);

        $this->customers->delete($customer);

        return response()->noContent();
    }
}
