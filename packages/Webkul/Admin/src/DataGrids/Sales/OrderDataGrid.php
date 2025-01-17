<?php

namespace Webkul\Admin\DataGrids\Sales;

use Illuminate\Support\Facades\DB;
use Webkul\Sales\Models\OrderAddress;
use Webkul\DataGrid\DataGrid;
use Webkul\Sales\Repositories\OrderRepository;

class OrderDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('orders')
            ->leftJoin('addresses as order_address_shipping', function ($leftJoin) {
                $leftJoin->on('order_address_shipping.order_id', '=', 'orders.id')
                    ->where('order_address_shipping.address_type', OrderAddress::ADDRESS_TYPE_SHIPPING);
            })
            ->leftJoin('addresses as order_address_billing', function ($leftJoin) {
                $leftJoin->on('order_address_billing.order_id', '=', 'orders.id')
                    ->where('order_address_billing.address_type', OrderAddress::ADDRESS_TYPE_BILLING);
            })
            ->leftJoin('order_payment', 'orders.id', '=', 'order_payment.order_id')
            ->select(
                'orders.id',
                'order_payment.method',
                'orders.increment_id',
                'orders.base_grand_total',
                'orders.created_at',
                'channel_name',
                'status',
                'customer_email',
                'orders.cart_id as image',
                DB::raw('CONCAT(' . DB::getTablePrefix() . 'orders.customer_first_name, " ", ' . DB::getTablePrefix() . 'orders.customer_last_name) as full_name'),
                DB::raw('CONCAT(' . DB::getTablePrefix() . 'order_address_billing.city, ", ", ' . DB::getTablePrefix() . 'order_address_billing.state,", ", ' . DB::getTablePrefix() . 'order_address_billing.country) as location')
            );

        $this->addFilter('full_name', DB::raw('CONCAT(' . DB::getTablePrefix() . 'orders.customer_first_name, " ", ' . DB::getTablePrefix() . 'orders.customer_last_name)'));
        $this->addFilter('created_at', 'orders.created_at');

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
            'index'      => 'increment_id',
            'label'      => trans('admin::app.sales.orders.index.datagrid.order-id'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'status',
            'label'      => trans('admin::app.sales.orders.index.datagrid.status'),
            'type'       => 'checkbox',
            'options'    => [
                'processing'      => trans('admin::app.sales.orders.index.datagrid.processing'),
                'completed'       => trans('admin::app.sales.orders.index.datagrid.completed'),
                'canceled'        => trans('admin::app.sales.orders.index.datagrid.canceled'),
                'closed'          => trans('admin::app.sales.orders.index.datagrid.closed'),
                'pending'         => trans('admin::app.sales.orders.index.datagrid.pending'),
                'pending_payment' => trans('admin::app.sales.orders.index.datagrid.pending-payment'),
                'fraud'           => trans('admin::app.sales.orders.index.datagrid.fraud'),
            ],
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                switch ($row->status) {
                    case 'processing':
                        return '<p class="label-processing">' . trans('admin::app.sales.orders.index.datagrid.processing') . '</p>';

                    case 'completed':
                        return '<p class="label-active">' . trans('admin::app.sales.orders.index.datagrid.completed') . '</p>';

                    case 'canceled':
                        return '<p class="label-cancelled">' . trans('admin::app.sales.orders.index.datagrid.canceled') . '</p>';

                    case 'closed':
                        return '<p class="label-closed">' . trans('admin::app.sales.orders.index.datagrid.closed') . '</p>';

                    case 'pending':
                        return '<p class="label-pending">' . trans('admin::app.sales.orders.index.datagrid.pending') . '</p>';

                    case 'pending_payment':
                        return '<p class="label-pending">' . trans('admin::app.sales.orders.index.datagrid.pending-payment') . '</p>';

                    case 'fraud':
                        return '<p class="label-cancelled">' . trans('admin::app.sales.orders.index.datagrid.fraud') . '</p>';
                }
            },
        ]);

        $this->addColumn([
            'index'      => 'base_grand_total',
            'label'      => trans('admin::app.sales.orders.index.datagrid.grand-total'),
            'type'       => 'price',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'method',
            'label'      => trans('admin::app.sales.orders.index.datagrid.pay-via'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => function ($row) {
                return core()->getConfigData('sales.payment_methods.' . $row->method . '.title');
            },
        ]);

        $this->addColumn([
            'index'      => 'channel_name',
            'label'      => trans('admin::app.sales.orders.index.datagrid.channel-name'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'full_name',
            'label'      => trans('admin::app.sales.orders.index.datagrid.customer'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'customer_email',
            'label'      => trans('admin::app.sales.orders.index.datagrid.email'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'location',
            'label'      => trans('admin::app.sales.orders.index.datagrid.location'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'image',
            'label'      => trans('admin::app.sales.orders.index.datagrid.images'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => function ($value) {
                $order = app(OrderRepository::class)->with('items')->find($value->id);

                return view('admin::sales.orders.images', compact('order'))->render();
            },
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => trans('admin::app.sales.orders.index.datagrid.date'),
            'type'       => 'date_range',
            'searchable' => false,
            'filterable' => true,
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
        if (bouncer()->hasPermission('sales.orders.view')) {
            $this->addAction([
                'icon'   => 'icon-view',
                'title'  => trans('admin::app.sales.orders.index.datagrid.view'),
                'method' => 'GET',
                'url'    => function ($row) {
                    return route('admin.sales.orders.view', $row->id);
                },
            ]);
        }
    }
}
