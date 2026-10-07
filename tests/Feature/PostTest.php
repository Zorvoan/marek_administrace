<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Listing ----------

    public function test_guests_can_see_posts_newest_first(): void
    {
        $old = Post::factory()->create(['title' => 'Old post']);
        $new = Post::factory()->create(['title' => 'New post']);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([$new->title, $old->title])
            ->assertSee($new->user->name)
            ->assertSee($new->category->name);
    }

    public function test_empty_list_shows_a_message(): void
    {
        $this->get('/')->assertOk()->assertSee('No posts found.');
    }

    public function test_list_is_paginated_and_pages_keep_the_search(): void
    {
        Post::factory()->count(12)->sequence(fn ($s) => ['title' => "Alpha {$s->index}"])->create();
        Post::factory()->create(['title' => 'Beta']);

        // Newest first: page 1 is Beta + Alpha 11..3, page 2 is Alpha 2..0.
        $this->get('/')->assertOk()->assertSee('Beta')->assertSee('Alpha 3')->assertDontSee('Alpha 2');
        $this->get('/?page=2')->assertOk()->assertSee('Alpha 2')->assertSee('Alpha 0')->assertDontSee('Alpha 3');

        // Filtered: page 1 is Alpha 11..2, page 2 is Alpha 1..0; the link keeps the search.
        $this->get('/?q=Alpha')->assertOk()->assertSee('q=Alpha&amp;page=2', false)->assertDontSee('Beta');
        $this->get('/?q=Alpha&page=2')->assertOk()->assertSee('Alpha 0')->assertDontSee('Alpha 2');
    }

    public function test_post_content_is_escaped(): void
    {
        $post = Post::factory()->create([
            'title' => '<script>alert(1)</script>',
            'body' => '<img src=x onerror=alert(2)>',
        ]);

        foreach (['/', "/posts/{$post->id}"] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertDontSee('<img src=x', false)
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        }
    }

    // ---------- Search and category filter ----------

    public function test_search_matches_title_or_body_case_insensitively(): void
    {
        Post::factory()->create(['title' => 'Learning Laravel', 'body' => 'nothing here']);
        Post::factory()->create(['title' => 'Cooking', 'body' => 'I love LARAVEL too']);
        Post::factory()->create(['title' => 'Gardening', 'body' => 'roses']);

        $this->get('/?q=laravel')
            ->assertOk()
            ->assertSee('Learning Laravel')
            ->assertSee('Cooking')
            ->assertDontSee('Gardening')
            ->assertSee('2 posts found');
    }

    public function test_search_requires_every_word(): void
    {
        Post::factory()->create(['title' => 'Red apples', 'body' => 'sweet']);
        Post::factory()->create(['title' => 'Green apples', 'body' => 'sour']);

        $this->get('/?q=apples+sour')->assertSee('Green apples')->assertDontSee('Red apples');
        $this->get('/?q=sour+apples')->assertSee('Green apples')->assertDontSee('Red apples');
        $this->get('/?q=apples+missing')->assertSee('No posts found.');
    }

    public function test_search_treats_wildcards_literally(): void
    {
        Post::factory()->create(['title' => '100% legit', 'body' => 'x']);
        Post::factory()->create(['title' => '1000 legit', 'body' => 'x']);
        Post::factory()->create(['title' => 'a_c', 'body' => 'x']);
        Post::factory()->create(['title' => 'abc', 'body' => 'x']);
        Post::factory()->create(['title' => 'bang! it', 'body' => 'x']);

        $this->get('/?q=100%25')->assertSee('100% legit')->assertDontSee('1000 legit');
        $this->get('/?q=a_c')->assertSee('a_c')->assertDontSee('abc');
        $this->get('/?q=bang!')->assertSee('bang! it')->assertDontSee('abc');
    }

    public function test_search_handles_unicode_text(): void
    {
        Post::factory()->create(['title' => 'Příliš žluťoučký kůň', 'body' => 'x']);
        Post::factory()->create(['title' => 'Something else', 'body' => 'x']);

        $this->get('/?q='.urlencode('žluťoučký'))->assertSee('Příliš žluťoučký kůň')->assertDontSee('Something else');
    }

    public function test_search_by_category_and_combined_with_text(): void
    {
        $tech = Category::factory()->create(['name' => 'Tech']);
        $life = Category::factory()->create(['name' => 'Life']);
        Post::factory()->for($tech)->create(['title' => 'Tech news']);
        Post::factory()->for($tech)->create(['title' => 'Tech gadgets']);
        Post::factory()->for($life)->create(['title' => 'Life news']);

        $this->get("/?category={$tech->id}")
            ->assertSee('Tech news')->assertSee('Tech gadgets')->assertDontSee('Life news')
            ->assertSee('in <strong>Tech</strong>', false);

        $this->get("/?category={$tech->id}&q=news")
            ->assertSee('Tech news')->assertDontSee('Tech gadgets')->assertDontSee('Life news');

        $this->get("/?category={$life->id}&q=gadgets")->assertSee('No posts found.');
        $this->get('/?category=999999')->assertOk()->assertSee('No posts found.');
        $this->get('/?category=&q=')->assertSee('Tech news')->assertSee('Life news');
    }

    public function test_search_input_is_validated(): void
    {
        $this->get('/?q[]=x')->assertSessionHasErrors('q');
        $this->get('/?q='.str_repeat('a', 101))->assertSessionHasErrors('q');
        $this->get('/?category=abc')->assertSessionHasErrors('category');
        $this->get('/?category[]=1')->assertSessionHasErrors('category');
        $this->get('/?q='.str_repeat('a', 100))->assertOk();
    }

    public function test_navbar_search_form_lists_categories_and_keeps_the_current_search(): void
    {
        $tech = Category::factory()->create(['name' => 'Tech']);
        Category::factory()->create(['name' => 'Life']);

        $this->get("/?q=hello&category={$tech->id}")
            ->assertSee('name="q" value="hello"', false)
            ->assertSee("<option value=\"{$tech->id}\" selected>Tech</option>", false)
            ->assertSee('Life');

        // Other pages never break on odd query strings.
        $this->get('/categories?q[]=x&category[]=1')->assertOk();
    }

    public function test_navbar_differs_for_guests_and_users(): void
    {
        $this->get('/')->assertSee('Log in')->assertSee('Sign up')->assertDontSee('New post')->assertDontSee('Log out');

        $this->actingAs(User::factory()->create(['name' => 'Jane Doe']))
            ->get('/')
            ->assertSee('New post')->assertSee('Jane Doe')->assertSee('Log out')->assertDontSee('Sign up');
    }

    // ---------- Viewing ----------

    public function test_guests_can_read_a_post(): void
    {
        $post = Post::factory()->create(['title' => 'Hello', 'body' => "Line one\nLine two"]);

        $this->get("/posts/{$post->id}")->assertOk()->assertSee('Hello')->assertSee("Line one\nLine two");
        $this->get('/posts/999999')->assertNotFound()->assertSee('Back to home');
    }

    // ---------- Creating ----------

    public function test_guests_cannot_create_posts(): void
    {
        $category = Category::factory()->create();

        $this->get('/posts/create')->assertRedirect(route('login'));
        $this->post('/posts', ['title' => 'T', 'body' => 'B', 'category_id' => $category->id])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_user_can_create_a_post_and_becomes_its_author(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Tech']);

        $this->actingAs($user)->get('/posts/create')->assertOk()->assertSee('Tech');

        $response = $this->actingAs($user)->post('/posts', [
            'title' => 'My first post',
            'body' => 'Some text',
            'category_id' => $category->id,
            'user_id' => $other->id, // must be ignored
        ]);

        $post = Post::firstOrFail();
        $response->assertRedirect(route('posts.show', $post));
        $this->assertSame($user->id, $post->user_id);
        $this->assertSame('My first post', $post->title);
        $this->assertSame($category->id, $post->category_id);
    }

    public function test_post_is_validated(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $valid = ['title' => 'T', 'body' => 'B', 'category_id' => $category->id];

        $this->actingAs($user);
        $this->post('/posts', ['title' => ''] + $valid)->assertSessionHasErrors('title');
        $this->post('/posts', ['title' => str_repeat('a', 256)] + $valid)->assertSessionHasErrors('title');
        $this->post('/posts', ['body' => '   '] + $valid)->assertSessionHasErrors('body');
        $this->post('/posts', ['body' => str_repeat('a', 10001)] + $valid)->assertSessionHasErrors('body');
        $this->post('/posts', ['category_id' => ''] + $valid)->assertSessionHasErrors('category_id');
        $this->post('/posts', ['category_id' => 999999] + $valid)->assertSessionHasErrors('category_id');
        $this->post('/posts', ['category_id' => [1]] + $valid)->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('posts', 0);
    }

    // ---------- Editing and deleting: authors only ----------

    public function test_author_can_edit_and_update_own_post(): void
    {
        $post = Post::factory()->create(['title' => 'Before']);
        $newCategory = Category::factory()->create();

        $this->actingAs($post->user)->get("/posts/{$post->id}/edit")->assertOk()->assertSee('Before');

        $this->actingAs($post->user)
            ->put("/posts/{$post->id}", ['title' => 'After', 'body' => 'New body', 'category_id' => $newCategory->id])
            ->assertRedirect(route('posts.show', $post));

        $post->refresh();
        $this->assertSame('After', $post->title);
        $this->assertSame('New body', $post->body);
        $this->assertSame($newCategory->id, $post->category_id);
    }

    public function test_update_is_validated_and_cannot_change_the_author(): void
    {
        $post = Post::factory()->create(['title' => 'Before']);
        $other = User::factory()->create();

        $this->actingAs($post->user)
            ->put("/posts/{$post->id}", ['title' => '', 'body' => 'x', 'category_id' => $post->category_id])
            ->assertSessionHasErrors('title');

        $this->actingAs($post->user)
            ->put("/posts/{$post->id}", ['title' => 'After', 'body' => 'x', 'category_id' => $post->category_id, 'user_id' => $other->id]);

        $post->refresh();
        $this->assertSame('After', $post->title);
        $this->assertNotSame($other->id, $post->user_id);
    }

    public function test_author_can_delete_own_post(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($post->user)
            ->delete("/posts/{$post->id}")
            ->assertRedirect(route('posts.index'));

        $this->assertModelMissing($post);
    }

    public function test_other_users_cannot_edit_update_or_delete_a_post(): void
    {
        $post = Post::factory()->create(['title' => 'Mine']);
        $intruder = User::factory()->create();
        $payload = ['title' => 'Hacked', 'body' => 'Hacked', 'category_id' => $post->category_id];

        $this->actingAs($intruder);
        $this->get("/posts/{$post->id}/edit")->assertForbidden();
        $this->put("/posts/{$post->id}", $payload)->assertForbidden();
        $this->delete("/posts/{$post->id}")->assertForbidden();

        $this->assertSame('Mine', $post->fresh()->title);
    }

    public function test_guests_cannot_edit_update_or_delete_a_post(): void
    {
        $post = Post::factory()->create(['title' => 'Mine']);
        $payload = ['title' => 'Hacked', 'body' => 'Hacked', 'category_id' => $post->category_id];

        $this->get("/posts/{$post->id}/edit")->assertRedirect(route('login'));
        $this->put("/posts/{$post->id}", $payload)->assertRedirect(route('login'));
        $this->delete("/posts/{$post->id}")->assertRedirect(route('login'));

        $this->assertSame('Mine', $post->fresh()->title);
    }

    public function test_edit_and_delete_buttons_are_only_shown_to_the_author(): void
    {
        $post = Post::factory()->create();
        $url = "/posts/{$post->id}";
        $editLink = "/posts/{$post->id}/edit";

        $this->get($url)->assertOk()->assertDontSee($editLink)->assertDontSee('Delete');

        $this->actingAs(User::factory()->create())->get($url)->assertOk()->assertDontSee($editLink)->assertDontSee('Delete');

        $this->actingAs($post->user)->get($url)->assertOk()->assertSee($editLink)->assertSee('Yes, delete')
            ->assertDontSee('onsubmit', false); // no inline JavaScript: the strict CSP would block it
    }
}
