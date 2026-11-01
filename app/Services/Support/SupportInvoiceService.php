<?php

namespace App\Services\Support;

use App\Enums\Invoices\InvoiceStatusEnum;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceItem;
use App\Models\Invoices\InvoiceNumberSequence;
use App\Models\Invoices\RecurringInvoice;
use App\Models\Support\SupportOrder;
use App\Models\Support\SupportProject;
use App\Services\Invoices\InvoiceCalculationService;
use App\Services\Invoices\RecurringInvoiceGenerationService;
use Illuminate\Validation\ValidationException;

class SupportInvoiceService
{
    public function invoice(SupportOrder $order, bool $includePending = false): ?Invoice
    {
        return $order->getConnection()->transaction(function () use ($order, $includePending): ?Invoice {
            $order = SupportOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->invoice_id) {
                return Invoice::withoutGlobalScope('active_company')->findOrFail($order->invoice_id);
            }
            if ((int) $order->final_minor === 0) {
                return null;
            }
            $project = SupportProject::query()->lockForUpdate()->findOrFail($order->project_id);
            abort_unless($order->currency === $project->currency, 409, __('support.currency_locked'));
            $template = $this->template($project);
            $template->items_template = [];
            $template->auto_send = false;
            $invoice = app(RecurringInvoiceGenerationService::class)->generate($template);
            $this->append($invoice, $order);
            if ($includePending) {
                foreach ($project->orders()->where('status', 'confirmed')->whereNull('invoice_id')->where('final_minor', '>', 0)->whereNotIn('kind', ['plan_fee', 'plan_change', 'hosting'])->lockForUpdate()->get() as $pending) {
                    $this->append($invoice, $pending);
                }
            }
            app(InvoiceCalculationService::class)->recalculateInvoice($invoice->refresh());

            return $invoice->refresh();
        });
    }

    public function template(SupportProject $project): RecurringInvoice
    {
        if ($project->recurring_invoice_id) {
            $template = RecurringInvoice::withoutGlobalScope('active_company')->findOrFail($project->recurring_invoice_id);
            if ($template->company_id !== $project->company_id || $template->customer_id !== $project->customer_id || $template->currency->value !== $project->currency) {
                throw ValidationException::withMessages(['invoice' => __('support.invoice_setup')]);
            }

            return $template;
        }
        $sequence = InvoiceNumberSequence::withoutGlobalScope('active_company')->where('company_id', $project->company_id)->orderByDesc('is_default')->first();
        if (! $sequence) {
            throw ValidationException::withMessages(['invoice' => __('support.invoice_setup')]);
        }
        $subscription = $project->subscription()->firstOrFail();
        $template = RecurringInvoice::query()->create([
            'company_id' => $project->company_id, 'customer_id' => $project->customer_id, 'invoice_number_sequence_id' => $sequence->id,
            'name' => 'Podpora: '.$project->name, 'is_active' => false, 'interval' => $subscription->interval,
            'day_of_month' => $subscription->anchor_day, 'month_of_year' => $subscription->anchor_month,
            'start_date' => $subscription->started_at->toDateString(), 'currency' => $project->currency,
            'due_days' => 14, 'items_template' => [], 'auto_send' => false, 'email_locale' => $project->locale,
        ]);
        $project->update(['recurring_invoice_id' => $template->id]);

        return $template;
    }

    private function append(Invoice $invoice, SupportOrder $order): void
    {
        abort_unless($invoice->status === InvoiceStatusEnum::NEW, 409);
        abort_unless($invoice->currency === $order->currency, 409, __('support.currency_locked'));
        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id, 'quantity' => 1, 'unit' => 'ks', 'unit_price' => $order->final_minor / 100,
            'vat_type' => 'zero_rate', 'vat_rate_value' => 0, 'sort_order' => $invoice->items()->count(),
        ]);
        $item->translations()->create(['locale' => $order->project->locale, 'description' => $order->scope]);
        $order->update(['invoice_id' => $invoice->id, 'invoice_item_id' => $item->id, 'status' => 'invoiced']);
    }
}
