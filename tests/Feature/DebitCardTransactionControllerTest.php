<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;
    protected $routePath = '/api/debit-card-transactions';
    protected $tableName = 'debit_card_transactions';
    protected $debitCardsTableName = 'debit_cards';
    protected $debitCardTransactionKeys = ['amount', 'currency_code'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    protected function createDebitCardTransactions()
    {
        $transactions = [2000, 3000, 4000, 5000, 60000];
        $debitCardTransactions = [];

        foreach ($transactions as $transaction) {
            $debitCardTransactions[] = [
                'amount' => $transaction,
                'currency_code' => 'IDR',
                'debit_card_id' => $this->debitCard->id
            ];
        }

        $createdDebitCardTransactions =  DebitCardTransaction::insert($debitCardTransactions);

        $this->assertDatabaseCount($this->tableName, 5);

        return $createdDebitCardTransactions;
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // get /debit-card-transactions
        $this->createDebitCardTransactions();

        $response = $this->get($this->routePath . '?debit_card_id=' . $this->debitCard->id);

        $response->assertStatus(200);

        $transactions = $response->json();

        foreach ($transactions as $transaction) {
            $arrayKeys = array_keys($transaction);
            foreach ($arrayKeys as $arrayKey) {
                $this->assertContains($arrayKey, $this->debitCardTransactionKeys);
            }
            $this->assertIsNumeric($transaction['amount']);
            $this->assertIsString($transaction['currency_code']);
        }
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $this->createDebitCardTransactions();

        $newUser = User::factory()->create();
        $newLoggedInUser = Passport::actingAs($newUser);
        $response = $this->actingAs($newLoggedInUser)->get($this->routePath . '?debit_card_id=' . $this->debitCard->id);

        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions
        $response = $this->post($this->routePath . '?debit_card_id=' . $this->debitCard->id, [
            'amount' => 1000,
            'currency_code' => 'IDR'
        ]);

        $this->assertDatabaseCount($this->tableName, 1);

        $response->assertStatus(201)->assertJsonStructure($this->debitCardTransactionKeys);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions

        $this->createDebitCardTransactions();

        $newUser = User::factory()->create();
        $newLoggedInUser = Passport::actingAs($newUser);
        $response = $this->actingAs($newLoggedInUser)->post($this->routePath . '?debit_card_id=' . $this->debitCard->id, [
            'amount' => 1000,
            'currency_code' => 'IDR'
        ]);

        $response->assertStatus(403);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $this->createDebitCardTransactions();

        $response = $this->get($this->routePath . '/' . 1);

        $response->assertStatus(200)->assertJsonStructure($this->debitCardTransactionKeys);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $this->createDebitCardTransactions();

        $newUser = User::factory()->create();

        $newLoggedInUser = Passport::actingAs($newUser);

        $response = $this->actingAs($newLoggedInUser)->get($this->routePath . '/' . 1);


        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
}
