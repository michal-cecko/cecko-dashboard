<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('recurring_invoice_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('locale', 5)->default('sk');
            $table->string('currency', 3)->default('EUR');
            $table->string('github_repo')->nullable();
            $table->unsignedBigInteger('github_installation_id')->nullable();
            $table->string('sentry_project_slug')->nullable()->unique();
            $table->timestamp('warranty_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('leave_ranges')->nullable();
            $table->timestamps();
            $table->unique(['github_repo', 'github_installation_id']);
        });
        Schema::create('support_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('support_contact_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('support_contact_id')->constrained()->restrictOnDelete();
            $table->boolean('can_approve')->default(false);
            $table->timestamps();
            $table->unique(['support_project_id', 'support_contact_id']);
        });
        Schema::create('support_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('support_projects')->restrictOnDelete();
            $table->string('plan')->default('none');
            $table->string('interval')->default('monthly');
            $table->unsignedTinyInteger('anchor_day');
            $table->unsignedTinyInteger('anchor_month');
            $table->timestamp('started_at');
            $table->timestamp('paid_period_start');
            $table->timestamp('paid_period_end');
            $table->timestamp('next_entitlement_at');
            $table->json('pricing_snapshot');
            $table->string('pending_plan')->nullable();
            $table->boolean('pending_cancel')->default(false);
            $table->timestamp('pending_change_at')->nullable();
            $table->timestamp('plan_ended_at')->nullable();
            $table->timestamp('hosting_period_start')->nullable();
            $table->timestamp('hosting_period_end')->nullable();
            $table->unsignedBigInteger('hosting_price_minor')->default(0);
            $table->json('scheduled_price_snapshot')->nullable();
            $table->timestamp('price_notice_at')->nullable();
            $table->timestamps();
        });
        Schema::create('support_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('support_projects')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('plan');
            $table->json('pricing_snapshot');
            foreach (['granted_minutes', 'used_minutes', 'granted_credit_minor', 'used_credit_minor', 'granted_call_minutes', 'used_call_minutes', 'granted_planning_minutes', 'used_planning_minutes'] as $column) {
                $table->unsignedBigInteger($column)->default(0);
            }
            $table->timestamps();
            $table->unique(['project_id', 'starts_at']);
        });
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('support_projects')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('support_contacts')->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('url', 2048)->nullable();
            $table->string('type')->default('question');
            $table->boolean('is_critical')->default(false);
            $table->string('cause')->default('unknown');
            $table->string('status')->default('new');
            $table->unsignedInteger('queue_position')->default(0);
            $table->json('checklist')->nullable();
            $table->unsignedInteger('internal_estimate_minutes')->nullable();
            $table->unsignedBigInteger('github_issue_id')->nullable()->unique();
            $table->unsignedBigInteger('github_issue_number')->nullable();
            $table->string('sync_status')->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_contact_id')->nullable()->constrained('support_contacts')->restrictOnDelete();
            $table->unsignedInteger('proposal_version')->default(1);
            $table->timestamps();
            $table->unique(['project_id', 'github_issue_number']);
            $table->index(['project_id', 'status', 'queue_position']);
        });
        Schema::create('support_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('support_requests')->restrictOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('provider_comment_id')->nullable()->unique();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('support_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('support_requests')->restrictOnDelete();
            $table->string('kind');
            $table->text('body')->default('');
            $table->foreignId('contact_id')->nullable()->constrained('support_contacts')->restrictOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('provider_comment_id')->nullable()->unique();
            $table->unsignedInteger('source_version')->default(1);
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
        Schema::create('support_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('support_projects')->restrictOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('support_requests')->restrictOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('support_periods')->restrictOnDelete();
            $table->string('kind');
            $table->unsignedInteger('version')->default(1);
            $table->text('scope');
            $table->json('terms');
            $table->unsignedInteger('approved_minutes')->nullable();
            foreach (['quoted_minor', 'discount_minor', 'credit_minor', 'final_minor'] as $column) {
                $table->unsignedBigInteger($column)->default(0);
            }
            $table->string('currency', 3);
            $table->string('status')->default('pending_email');
            $table->foreignId('confirmed_contact_id')->nullable()->constrained('support_contacts')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('operation_key')->unique();
            $table->timestamps();
        });
        Schema::create('support_login_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('support_projects')->restrictOnDelete();
            $table->foreignId('contact_id')->constrained('support_contacts')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('support_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('support_projects')->restrictOnDelete();
            $table->foreignId('contact_id')->constrained('support_contacts')->restrictOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('support_requests')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('support_orders')->restrictOnDelete();
            $table->string('purpose');
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('email');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
        Schema::create('support_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('support_projects')->restrictOnDelete();
            $table->foreignId('request_id')->constrained('support_requests')->restrictOnDelete();
            $table->string('kind');
            $table->uuid('operation_key')->unique();
            $table->json('payload');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('state')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('pending_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('support_projects')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('support_contacts')->restrictOnDelete();
            $table->string('channel');
            $table->string('delivery_mode')->default('batch');
            $table->string('batch_key');
            $table->string('recipient');
            $table->string('type');
            $table->string('label');
            $table->string('url', 2048);
            $table->nullableMorphs('source');
            $table->string('notification_key')->unique();
            $table->timestamp('send_after');
            $table->timestamp('claimed_at')->nullable();
            $table->uuid('claimed_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->timestamps();
            $table->index(['sent_at', 'claimed_at', 'send_after']);
        });
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('delivery_key');
            $table->foreignId('project_id')->constrained('support_projects')->restrictOnDelete();
            $table->string('event_type');
            $table->text('payload');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'delivery_key']);
        });
        Schema::create('support_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('support_projects')->restrictOnDelete();
            $table->date('month');
            $table->json('sections');
            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'month']);
        });
    }

    public function down(): void
    {
        foreach (['support_reports', 'webhook_deliveries', 'pending_notifications', 'support_sync_operations', 'support_confirmations', 'support_login_links', 'support_orders', 'support_request_events', 'support_internal_notes', 'support_requests', 'support_periods', 'support_subscriptions', 'support_contact_project', 'support_contacts', 'support_projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
