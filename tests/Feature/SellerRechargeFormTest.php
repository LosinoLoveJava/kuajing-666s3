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
            'amount' => '100.04', 'currency' => 'TRY', 'amount_local' => '5052.02', 'trx_id' => 'TEST-RECHARGE'];
    }

    public function testBankDetailsAreSavedForReviewAndBalanceIsNotCredited()
    {
        $this->post('/offline-wallet-recharge', $this->payload())
            ->assertRedirect(route('seller.money_withdraw_requests.index'));
        $wallet = DB::table('wallets')->first();
        $this->assertSame('Bank', $wallet->payment_method);
        $this->assertEquals(0, $wallet->approval);
        $this->assertStringContainsString('Account number: 00123456', $wallet->payment_details);
        $this->assertStringContainsString('TRY: 5052.02', $wallet->payment_details);
        $this->assertEquals(100.04, $wallet->amount);
        $this->assertEquals(10000, auth()->user()->balance);
    }

    public function testIbanDetailsAreSavedForManualReview()
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

    public function testArbitraryIbanAndMissingBankAccountAreAllowedForManualReview()
    {
        $data = $this->payload();
        unset($data['bank_account']);
        $data = array_merge($data, ['recharge_method' => 'IBAN', 'iban_country' => '中国', 'iban_number' => 'AE000', 'iban_bic'=>'123']);
        $this->post('/offline-wallet-recharge', $data)->assertRedirect(route('seller.money_withdraw_requests.index'));
        $wallet = DB::table('wallets')->first();
        $this->assertStringContainsString('IBAN: AE000', $wallet->payment_details);
        $this->assertStringContainsString('SWIFT / BIC: 123', $wallet->payment_details);
        $this->assertEquals(0, $wallet->approval);
        $this->assertEquals(10000, auth()->user()->fresh()->balance);
    }

    public function testSellerModalOffersBankAndCryptoWithCurrencyLogos()
    {
        $this->post('/offline-wallet-recharge-modal', ['type' => 1])->assertOk()
            ->assertSee('name="recharge_method"', false)->assertSee('value="Crypto"', false)
            ->assertSee('value="IBAN"', false)->assertSee('name="currency"', false)->assertSee('name="amount_local"', false)
            ->assertDontSee('value="SGD"', false)->assertSee('value="TRY"', false)
            ->assertSee('Test transfer instructions')->assertDontSee('name="amount_aed"', false)->assertDontSee('aiz-megabox')
            ->assertSee('USDT_TRC20')->assertSee('USDT_ERC20')->assertSee('currencies/btc.svg')->assertSee('currencies/trx.svg')
            ->assertSee('name="request_token"', false)->assertDontSee('data-iban-required');
    }

    public function testDirectSingaporeRechargeIsRejectedBeforeCreatingARecord()
    {
        foreach ([['currency'=>'SGD'], ['amount_sgd'=>'128.05'], ['amount_sgd'=>'']] as $retired) {
            $this->postJson('/offline-wallet-recharge', array_merge($this->payload(), $retired))
                ->assertStatus(422)->assertJsonValidationErrors(array_keys($retired));
        }
        $this->assertDatabaseCount('wallets', 0);
        $this->assertEquals(10000, auth()->user()->fresh()->balance);
    }
}
