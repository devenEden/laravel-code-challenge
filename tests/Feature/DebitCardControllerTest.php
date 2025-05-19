<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected $tableName = 'debit_cards';
    protected $routePath = '/api/debit-cards';
    protected $debitCardKeys = [
        'id',
        'number',
        'type',
        'expiration_date',
        'is_active'
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    protected function createDebitCard()
    {
        // get /debit-cards
        $debitCard = DebitCard::factory()->make();
        $debitCard->user_id = $this->user->id;

        $debitCard->save();

        $this->assertDatabaseHas($this->tableName, [
            'id' => $debitCard->id
        ]);

        return $debitCard;
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        $this->createDebitCard();

        $response  = $this->get($this->routePath);

        $response->assertStatus(200);

        $debitCards = $response->json();

        foreach ($debitCards as $debitCard) {
            $arrayKeys = array_keys($debitCard);
            foreach ($arrayKeys as $arrayKey) {
                $this->assertContains($arrayKey, $this->debitCardKeys);
            }
            $this->assertArrayHasKey('id', $debitCard);
            $this->assertArrayHasKey('number', $debitCard);
            $this->assertArrayHasKey('type', $debitCard);
            $this->assertArrayHasKey('expiration_date', $debitCard);
            $this->assertArrayHasKey('is_active', $debitCard);
        }
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards

        $this->createDebitCard();

        $newUser = User::factory()->create();
        $newLoggedInUser = Passport::actingAs($newUser);

        $response  = $this->actingAs($newLoggedInUser)->get($this->routePath);

        $debitCards = $response->json();

        $this->assertEmpty($debitCards);

        $currentUserResponse = $this->get($this->routePath);

        $debitCards = $currentUserResponse->json();

        foreach ($debitCards as $debitCard) {
            $db_card = DebitCard::find($debitCard['id']);
            $this->assertSame($db_card->user_id, $this->user->id);
        }
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $response  = $this->post($this->routePath, [
            'type' => 'Master Card'
        ]);

        $debitCard = $response->json();

        $this->assertDatabaseHas($this->tableName, [
            'id' => $debitCard['id']
        ]);

        $response->assertStatus(201)->assertJsonStructure($this->debitCardKeys);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $debitCard =  $this->createDebitCard();

        $response = $this->get($this->routePath . '/' . $debitCard->id);

        $response->assertStatus(200)->assertJsonStructure($this->debitCardKeys);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $response = $this->get($this->routePath . '/' . 1);;

        $response->assertStatus(404);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard =  $this->createDebitCard();
        $response  = $this->put($this->routePath . '/' . $debitCard->id, [
            'is_active' => true
        ]);

        $response->assertStatus(200)->assertJsonStructure($this->debitCardKeys);

        $activeCard = DebitCard::find($debitCard->id);

        $this->assertNull($activeCard->disabled_at);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard =  $this->createDebitCard();
        $response  = $this->put($this->routePath . '/' . $debitCard->id, [
            'is_active' => false
        ]);

        $response->assertStatus(200)->assertJsonStructure($this->debitCardKeys);

        $activeCard = DebitCard::find($debitCard->id);

        $this->assertNotNull($activeCard->disabled_at);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = $this->createDebitCard();
        $response  = $this->put($this->routePath . '/' . $debitCard->id, [
            'test' => false
        ]);

        $this->assertNotEquals(200, $response->status());

        $checkActiveTypeResponse  = $this->put($this->routePath . '/' . $debitCard->id, [
            'is_active' => 'false'
        ]);

        $this->assertNotEquals(200, $checkActiveTypeResponse->status());
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = $this->createDebitCard();
        $response  = $this->delete($this->routePath . '/' . $debitCard->id);

        $response->assertStatus(204);

        $this->assertSoftDeleted($this->tableName, [
            'id' => $debitCard->id
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = $this->createDebitCard()
            ->debitCardTransactions()
            ->create([
                'amount' => 2500,
                'currency_code' => 'UGX'
            ]);

        $response  = $this->delete($this->routePath . '/' . $debitCard->id);

        $this->assertNotEquals(204, $response->status());
        $this->assertNotSoftDeleted($this->tableName, [
            'id' => $debitCard->id
        ]);
        $this->assertDatabaseHas($this->tableName, [
            'id' => $debitCard->id
        ]);
    }

    // Extra bonus for extra tests :)
    protected function testCannotCreateDebitCardWithWrongValidation()
    {
        // get /debit-cards
        $response  = $this->post($this->routePath, [
            'name' => 'Master Card'
        ]);

        $this->assertNotEquals(201, $response->status());
    }
}
