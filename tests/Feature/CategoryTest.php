<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_categories_with_post_counts(): void
    {
        $tech = Category::factory()->create(['name' => 'Tech']);
        Category::factory()->create(['name' => 'Art']);
        Post::factory()->count(2)->for($tech)->create();

        $this->get('/categories')
            ->assertOk()
            ->assertSeeInOrder(['Art', '0 posts', 'Tech', '2 posts'])
            ->assertSee('to create a category');
    }

    public function test_guests_cannot_create_categories(): void
    {
        $this->post('/categories', ['name' => 'Sneaky'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_user_can_create_a_category(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/categories', ['name' => '  Cooking '])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Cooking']);
    }

    public function test_category_name_is_validated(): void
    {
        $this->actingAs(User::factory()->create());
        Category::factory()->create(['name' => 'Tech']);

        $this->post('/categories', ['name' => ''])->assertSessionHasErrors('name');
        $this->post('/categories', ['name' => 'Tech'])->assertSessionHasErrors('name');
        $this->post('/categories', ['name' => str_repeat('a', 51)])->assertSessionHasErrors('name');
        $this->post('/categories', ['name' => ['x']])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('categories', 1);
    }
}
