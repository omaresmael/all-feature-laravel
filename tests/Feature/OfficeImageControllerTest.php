<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    use RefreshDatabase;
    /**
     * @test
    **/

    public function itStoresImageForOffice()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);
        $office = Office::factory()->for($user)->create();

        $response = $this->post('/api/offices/'.$office->id.'/images',[
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();
        Storage::disk('public')->assertExists(
            $response->json('data.path')
        );

    }

    /**
    @test
     **/
    public function itDeletesImage()
    {
        Storage::disk('public')->put('/office_image.jpg','empty');



        $user = User::factory()->createQuietly();

        $office = Office::factory()->for($user)->create();
        $image1 = $office->images()->create([
            'path' => 'featured image.png',

        ]);
        $image2 = $office->images()->create([
            'path' => 'office_image.jpg',
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id.'/images/'.$image2->id);

        $response->assertOk();
        $this->assertModelMissing($image2);

        Storage::disk('public')->assertMissing('office_image.jpg');


    }

    /**
    @test
     **/
    public function itDoesntDeleteTheOnlyImage()
    {
        Storage::disk('public')->put('/office_image.jpg','empty');



        $user = User::factory()->createQuietly();

        $office = Office::factory()->for($user)->create();
        $image1 = $office->images()->create([
            'path' => 'featured image.png',

        ]);


        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id.'/images/'.$image1->id);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['images' => 'Cannot delete the only image']);





    }

    /**
    @test
     **/
    public function itDoesntDeleteTheFeaturedImage()
    {
        Storage::disk('public')->put('/office_image.jpg','empty');



        $user = User::factory()->createQuietly();

        $office = Office::factory()->for($user)->create();
        $image1 = $office->images()->create([
            'path' => 'featured image.png',

        ]);
        $image2 = $office->images()->create([
            'path' => 'featured image.png',

        ]);
        $office->featured_image_id = $image1->id;
        $office->save();


        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id.'/images/'.$image1->id);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['images' => 'Cannot delete the featured image']);




    }

    /**
    @test
     **/
    public function itDoesntDeleteImageRelatedToAnotherOffice()
    {
        Storage::disk('public')->put('/office_image.jpg','empty');



        $user = User::factory()->createQuietly();

        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->create();
        $image1 = $office->images()->create([
            'path' => 'featured image.png',

        ]);
        $image2 = $office2->images()->create([
            'path' => 'featured image.png',
        ]);


        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id.'/images/'.$image2->id);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['images' => 'Cannot delete the image']);

    }
}
