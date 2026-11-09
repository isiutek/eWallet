<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number', 20)->unique(); // nomor dompet yang dibagikan ke pengguna lain
            $table->unsignedBigInteger('balance')->default(0); // dalam rupiah (IDR tanpa desimal)
            $table->string('currency', 3)->default('IDR');
            $table->string('status', 20)->default('active'); // active | frozen
            $table->timestamps();
        });

        // Ledger: setiap mutasi saldo = satu baris, tidak pernah diubah/dihapus.
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->index(); // dipakai bersama oleh kedua sisi transfer
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // topup | transfer | qr_payment | adjustment
            $table->string('direction', 6); // credit (masuk) | debit (keluar)
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->foreignId('counterparty_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->string('status', 20)->default('success');
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamps();

            $table->unique(['wallet_id', 'idempotency_key']);
            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('topups', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20); // manual | xendit
            $table->string('status', 20)->default('pending'); // pending | paid | rejected | expired | failed
            $table->string('proof_path')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('xendit_invoice_id')->nullable()->unique();
            $table->text('xendit_invoice_url')->nullable();
            $table->string('payment_channel')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'method']);
        });

        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->nullable(); // null = nominal diisi pembayar (QR statis)
            $table->string('description')->nullable();
            $table->boolean('single_use')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_reference', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('topups');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
    }
};
