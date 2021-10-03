<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    /**
     * @test
     */
    public function itListsAllOfficesInPaginatedWay()
    {
        Office::factory(3)->create();
        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3,'data');

        $this->assertNotNull($response->json('data')[0]['id']);
        $this->assertNotNull($response->json('meta'));
        $this->assertNotNull($response->json('links'));
    }

    /**
     @test
     */
    public function itOnlyListedOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(2)->create();
        Office::factory()->create(['hidden'=>true]);
        Office::factory()->create(['approval_status'=>Office::APPROVAL_PENDING]);

        $response = $this->get('/api/offices');

        $response->assertJsonCount(2,'data');
    }

    /**
    @test
     */
    public function itListsAllOfficesThatBelongsToTheCurrentLoggedInUser()
    {
        $user = User::factory()->create();
        Office::factory(3)->for($user)->create();
        Office::factory()->for($user)->create(['hidden'=>true]);
        Office::factory()->for($user)->create(['approval_status'=>Office::APPROVAL_PENDING]);
        $this->actingAs($user);
        $response = $this->get('/api/offices/?user_id='.$user->id);

        $response->assertJsonCount(5,'data');
    }

    /**
    @test
user */
    public function itFiltersByUserId()
    {
        Office::factory(3)->create();
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $response = $this->get(
            '/api/offices?user_id='.$user->id
        );

        $response->assertJsonCount(1,'data');
        $this->assertEquals($office->id,$response->json('data')[0]["id"]);
    }
    /**
    @test
     */
    public function itFiltersByVisitorId()
    {
        Office::factory(3)->create();
        $visitor = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for($office)->for($visitor)->create();
        Reservation::factory()->for(Office::factory()->create() )->for($office)->create();

        $response = $this->get(
            '/api/offices?visitor_id='.$visitor->id
        );

        $response->assertJsonCount(1,'data');
        $this->assertEquals($office->id,$response->json('data')[0]['id']);
    }
    /**
    @test
     */
    public function itIncludesImagesTagsUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
//        $image = Image::factory()->create();

        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path'=>'image.jpg']);
        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertEquals($response->json('data')[0]['user']['id'],$user->id);

    }

    /**
     * @test
     */
    public function itReturnsActiveReservations()
    {

        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create(['status'=>Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status'=>Reservation::STATUS_CANCELLED]);


        $response = $this->get('/api/offices');
        $response->assertOk();
        $this->assertEquals(1,$response->json('data')[0]['reservations_count']);
    }
    /**
     * @test
     */
    public function itOrdersByDistanceWhenCoordinatesAreProvided()
    {
        /*
           'lat' => '38.720661384644046',
           'lng' => '-9.16044783453807',
        */
        $office2 = Office::factory()->create([
            'lat' => '-10.720661384644046',
            'lng' => '-9.16044783453807',
            'title' => 'far Office'

        ]);

        $office = Office::factory()->create([
            'lat' => '39.720661384644046',
            'lng' => '-9.16044783453807',
            'title' => 'near Office'
        ]);

        $response = $this->get('/api/offices?lat=38.720661384644046&lng=-9.16044783453807');

        $this->assertEquals('near Office',$response->json('data')[0]['title']);
        $this->assertEquals('far Office',$response->json('data')[1]['title']);
    }

    /**
        @test
     **/
    public function itShowsOffice()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();


        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path'=>'image.jpg']);

        Reservation::factory()->for($office)->create(['status'=>Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status'=>Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices/'.$office->id);
        $response->assertOk();


        $this->assertIsArray($response->json('data')['images']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertEquals($response->json('data')['user']['id'],$user->id);


    }

    /**
    @test
     **/
    public function itCreatesOffice()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin'=>true]);

        $user = User::factory()->createQuietly();
        $tag = Tag::factory()->create();
        $tag2 = Tag::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('api/offices',[
            'title' => 'Office in libia',
            'description' => 'test',
            'lat' => '9.2365845425',
            'lng' => '-2.365814968',
            'address_line1' => 'address',
            'price_per_day' => 10000,
            'monthly_discount' => 5,
            'tags' => [
                $tag->id,
                $tag2->id,
            ]
        ]);

        $response->assertCreated()
        ->assertJsonPath('data.title','Office in libia')
        ->assertJsonPath('data.approval_status',Office::APPROVAL_PENDING)
        ->assertJsonPath('data.user.id',$user->id)
        ->assertJsonCount(2,'data.tags');

        $this->assertDatabaseHas('offices',[
            'title' => 'Office in libia'
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);


    }

    /**
    @test
     **/
    public function itDoesntAllowCreatingIfScopeNotProvided()
    {
        $user = User::factory()->createQuietly();
        $token = $user->createToken('test', []);

        $response = $this->postJson('api/offices',[],[
            'Authorization' => 'Bearer '.$token->plainTextToken
        ]);
        $response->assertStatus(403);

    }

    /**
    @test
     **/
    public function itUpdatesOffice()
    {
        $user = User::factory()->createQuietly();
        $tags = Tag::factory(2)->create();
        $anotherTag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->putJson('api/offices/'.$office->id,[
            'title' => 'Office in libia',
            'tags' => [$tags[0]->id,$anotherTag->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title','Office in libia')

            ->assertJsonPath('data.tags.0.id',$tags[0]->id)
            ->assertJsonPath('data.tags.1.id',$anotherTag->id);




    }
    /**
    @test
     **/
    public function itDoesntUpdateOfficeThatDoesntBelongToUser()
    {
        $user = User::factory()->createQuietly();
        $anotherUser = User::factory()->createQuietly();
        $tags = Tag::factory(2)->create();
        $office = Office::factory()->for($anotherUser)->create();
        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->putJson('api/offices/'.$office->id,[
            'title' => 'Office in libia',

        ]);

        $response->assertStatus(403);





    }

    /**
    @test
     **/
    public function itsPendingWhenCriticalAttributesChanges()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin'=>true]);
        $user = User::factory()->createQuietly();

        $tags = Tag::factory(2)->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->putJson('api/offices/'.$office->id,[
            'lat' => $this->faker->latitude,
            'lng' => $this->faker->longitude,
            'price_per_day' => 120,

        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.approval_status',Office::APPROVAL_PENDING);
        $this->assertDatabaseHas('offices',[
            'id' => $office->id,
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);




    }
    /**
    @test
     **/
    public function itDeletesOffices()
    {

        $user = User::factory()->createQuietly();

        $tags = Tag::factory(2)->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->deleteJson('api/offices/'.$office->id);

        $response->assertStatus(200);

    }

    /**
    @test
     **/
    public function itDoesntDeleteOfficesThatHasReservation()
    {
        $user = User::factory()->createQuietly();
        $visitor = User::factory()->createQuietly();

        $tags = Tag::factory(2)->create();
        $office = Office::factory()->for($user)->create();
        Reservation::factory()->for($office)->for($visitor)->create();
        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->deleteJson('api/offices/'.$office->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

    }
}
