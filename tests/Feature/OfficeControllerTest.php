<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;
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
        $response->dump();
        $response->assertJsonCount(2,'data');
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
        $response->dump();
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

        $response->dump();
        $this->assertIsArray($response->json('data')['images']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertEquals($response->json('data')['user']['id'],$user->id);


    }
}
