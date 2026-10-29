<?php
/**
 * فاکتورهای خرید و برگشتی خرید
 * از متدهای مشترک InvoiceController ارث‌بری می‌شود (نماهای index/view/print مشترک‌اند).
 */
declare(strict_types=1);
namespace App\Controllers;

class PurchaseController extends InvoiceController
{
    public function indexAction(): void
    {
        $this->requirePerm('invoices.view');
        $this->listView('purchase', 'فاکتورهای خرید', 'purchases', 'purchases/create', 'فاکتور خرید جدید');
    }

    public function returnsAction(): void
    {
        $this->requirePerm('invoices.view');
        $this->listView('return_purchase', 'فاکتورهای برگشتی خرید', 'purchases/returns', 'purchases/create-return', 'فاکتور برگشتی خرید');
    }

    public function createAction(): void
    {
        $this->requirePerm('invoices.edit');
        $this->formView('purchase');
    }

    public function createReturnAction(): void
    {
        $this->requirePerm('invoices.edit');
        $this->formView('return_purchase');
    }

    public function storeAction(): void
    {
        $this->requirePerm('invoices.edit');
        $this->store('purchase');
    }

    public function storeReturnAction(): void
    {
        $this->requirePerm('invoices.edit');
        $this->store('return_purchase');
    }

    /* viewAction / printAction / cancelAction از InvoiceController به ارث می‌رسد
       و نمای مشترک invoices/view و invoices/print را بر اساس invoice_type نمایش می‌دهد. */
}
