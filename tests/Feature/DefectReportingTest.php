<?php

use App\Enums\UserRole;
use App\Models\Defect;
use App\Models\DefectPhoto;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('defect_photos');

    $this->manager = User::factory()->create();
    $this->manager->role = UserRole::Manager;
    $this->manager->save();

    $this->owner = User::factory()->create();
    $this->owner->role = UserRole::Owner;
    $this->owner->save();

    $this->contractor = User::factory()->create();
    $this->contractor->role = UserRole::Contractor;
    $this->contractor->save();

    $this->property = new Property([
        'name' => 'Defect Test House',
        'address' => '123 Example Road',
    ]);
    $this->property->creator()->associate($this->manager);
    $this->property->save();

    $this->property->members()->attach($this->owner->id);

    $this->room = $this->property->rooms()->create([
        'name' => 'Bathroom',
    ]);

    $this->payload = [
        'title' => 'Leaking bathroom tap',
        'description' => 'Water leaks continuously from the tap.',
        'category' => 'plumbing',
        'room_id' => $this->room->id,
    ];

    $this->reportUrl = '/properties/'.$this->property->id.'/defects';
});

test('guests cannot submit defect reports', function () {
    $this->post($this->reportUrl, $this->payload)
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('defects', 0);
});

test('managers and linked owners can report defects with photos', function (
    string $actor,
) {
    $user = $this->{$actor};

    $this->actingAs($user)
        ->get($this->reportUrl.'/create')
        ->assertOk();

    $this->post($this->reportUrl, [
        ...$this->payload,
        'photos' => [
            UploadedFile::fake()->image('leak.jpg', 100, 100),
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $defect = Defect::query()->sole();
    $photo = DefectPhoto::query()->sole();

    $this->assertDatabaseHas('defects', [
        'id' => $defect->id,
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'reported_by' => $user->id,
        'status' => 'reported',
        'priority' => 'medium',
        'assigned_to' => null,
    ]);

    $this->assertDatabaseHas('defect_photos', [
        'id' => $photo->id,
        'defect_id' => $defect->id,
        'uploaded_by' => $user->id,
        'disk' => 'defect_photos',
        'type' => 'before',
    ]);

    Storage::disk('defect_photos')->assertExists($photo->path);

    $this->get('/defects/'.$defect->id)
        ->assertOk()
        ->assertSee('Leaking bathroom tap');

    $response = $this->get('/defect-photos/'.$photo->id);

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');
})->with(['manager', 'owner']);

test('unlinked owners and contractors cannot report defects', function (
    string $actor,
) {
    if ($actor === 'owner') {
        $this->property->members()->detach($this->owner->id);
    } else {
        $this->property->members()->attach($this->contractor->id);
    }

    $this->actingAs($this->{$actor})
        ->get($this->reportUrl.'/create')
        ->assertForbidden();

    $this->post($this->reportUrl, $this->payload)
        ->assertForbidden();

    $this->assertDatabaseCount('defects', 0);
})->with(['owner', 'contractor']);

test('reports reject rooms from another property', function () {
    $other = new Property([
        'name' => 'Other House',
        'address' => '456 Example Road',
    ]);
    $other->creator()->associate($this->manager);
    $other->save();

    $otherRoom = $other->rooms()->create(['name' => 'Kitchen']);

    $this->actingAs($this->owner)
        ->post($this->reportUrl, [
            ...$this->payload,
            'room_id' => $otherRoom->id,
        ])
        ->assertSessionHasErrors('room_id');

    $this->assertDatabaseCount('defects', 0);
});

test('reports can be submitted without photos and ignore privileged fields', function () {
    $this->actingAs($this->owner)
        ->post($this->reportUrl, [
            ...$this->payload,
            'status' => 'verified',
            'priority' => 'high',
            'assigned_to' => $this->contractor->id,
            'reported_by' => $this->manager->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('defects', [
        'reported_by' => $this->owner->id,
        'status' => 'reported',
        'priority' => 'medium',
        'assigned_to' => null,
    ]);

    $this->assertDatabaseCount('defects', 1);
    $this->assertDatabaseCount('defect_photos', 0);
});

test('invalid photos are rejected without saving a report', function (
    string $kind,
    string $errorKey,
) {
    $photos = match ($kind) {
        'not-image' => [
            UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ],
        'too-large' => [
            UploadedFile::fake()->image('large.jpg')->size(2049),
        ],
        'too-wide' => [
            UploadedFile::fake()->image('wide.jpg', 6001, 10),
        ],
        'too-many' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
            UploadedFile::fake()->image('three.jpg'),
            UploadedFile::fake()->image('four.jpg'),
        ],
    };

    $this->actingAs($this->owner)
        ->post($this->reportUrl, [
            ...$this->payload,
            'photos' => $photos,
        ])
        ->assertSessionHasErrors($errorKey);

    $this->assertDatabaseCount('defects', 0);
    $this->assertDatabaseCount('defect_photos', 0);

    expect(Storage::disk('defect_photos')->allFiles())->toBeEmpty();
})->with([
    'non-image file' => ['not-image', 'photos.0'],
    'oversized image' => ['too-large', 'photos.0'],
    'excessive dimensions' => ['too-wide', 'photos.0'],
    'more than three photos' => ['too-many', 'photos'],
]);

test('owners lose access to defects and photos when membership is removed', function () {
    $this->actingAs($this->owner)
        ->post($this->reportUrl, [
            ...$this->payload,
            'photos' => [UploadedFile::fake()->image('leak.jpg')],
        ])
        ->assertSessionHasNoErrors();

    $defect = Defect::query()->sole();
    $photo = DefectPhoto::query()->sole();

    $this->property->members()->detach($this->owner->id);

    $this->get('/defects/'.$defect->id)->assertForbidden();
    $this->get('/defect-photos/'.$photo->id)->assertForbidden();

    $this->get('/defects')
        ->assertOk()
        ->assertDontSee($this->payload['title']);
});

test('contractors can only access assigned defects and their photos', function () {
    $this->actingAs($this->manager)
        ->post($this->reportUrl, [
            ...$this->payload,
            'photos' => [UploadedFile::fake()->image('leak.jpg')],
        ])
        ->assertSessionHasNoErrors();

    $defect = Defect::query()->sole();
    $photo = DefectPhoto::query()->sole();

    $this->actingAs($this->contractor)
        ->get('/defects/'.$defect->id)
        ->assertForbidden();

    $this->get('/defect-photos/'.$photo->id)->assertForbidden();

    $this->get('/defects')
        ->assertOk()
        ->assertDontSee($this->payload['title']);

    $defect->assigned_to = $this->contractor->id;
    $defect->save();

    $this->get('/defects/'.$defect->id)->assertOk();
    $this->get('/defect-photos/'.$photo->id)->assertOk();

    $this->get('/defects')
        ->assertOk()
        ->assertSee($this->payload['title']);
});
