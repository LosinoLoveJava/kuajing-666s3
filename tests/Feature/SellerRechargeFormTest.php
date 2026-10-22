<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerRechargeFormTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        $this->withoutMiddleware();
        $user = new User;
        $user->forceFill(['id' => 692, 'name' => 'Test Seller', 'user_type' => 'seller', 'balance' => 10000]);
        $user->save();
        $this->actingAs($user);
    }

    private function payload()
    {
        return ['type' => 1, 'recharge_method' => 'Bank', 'bank_name' => 'Test Bank',
            'account_holder' => 'Test Holder', 'bank_account' => '00123456',
            'amount' => '100.04', 'amount_aed' => '367.15', 'trx_id' => 'TEST-RECHARGE'];
    }

    public function testBankDetailsAreSavedForReviewAndBalanceIsNotCredited()
    {
        $this->post('/offline-wallet-recharge', $this->payload())
            ->assertRedirect(route('seller.money_withdraw_requests.index'));
        $wallet = DB::table('wallets')->first();
        $this->assertSame('Bank', $wallet->payment_method);
        $this->assertEquals(0, $wallet->approval);
        $this->assertStringContainsString('Account number: 00123456', $wallet->payment_details);
        $this->assertStringContainsString('AED: 367.15', $wallet->payment_details);
        $this->assertEquals(100.04, $wallet->amount);
        $this->assertEquals(10000, auth()->user()->balance);
    }

    public function testIbanDetailsAreValidatedAndSaved()
    {
        $data = array_merge($this->payload(), ['recharge_method' => 'IBAN',
            'iban_country' => 'AE',
            'iban_number' => 'AE070331234567890123456']);
        unset($data['bank_account']);
        $this->post('/offline-wallet-recharge', $data)->assertRedirect(route('seller.money_withdraw_requests.index'));
        $wallet = DB::table('wallets')->first();
        $this->assertSame('IBAN', $wallet->payment_method);
        $this->assertStringContainsString('IBAN: AE070331234567890123456', $wallet->payment_details);
    }

    public function testInvalidIbanAndMissingBankAccountAreRejected()
    {
        $data = $this->payload();
        unset($data['bank_account']);
        $this->postJson('/offline-wallet-recharge', $data)->assertStatus(422)->assertJsonValidationErrors('bank_account');
        $data = array_merge($data, ['recharge_method' => 'IBAN', 'iban_country' => 'AE', 'iban_number' => 'AE000']);
        $this->postJson('/offline-wallet-recharge', $data)->assertStatus(422)->assertJsonValidationErrors('iban_number');
        $this->assertEquals(0, DB::table('wallets')->count());
    }

    public function testSellerModalUsesDropdownWithoutPaymentCards()
    {
        $this->post('/offline-wallet-recharge-modal', ['type' => 1])->assertOk()
            ->assertSee('name="recharge_method"', false)->assertSee('value="Bank"', false)
            ->assertSee('value="IBAN"', false)->assertSee('3.67')->assertSee('Test transfer instructions')->assertSee('name="amount_aed"', false)->assertDontSee('aiz-megabox');
    }
}
