<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_faq_page_is_public_and_lists_every_topic(): void
    {
        $response = $this->get(route('faq'))->assertOk();

        foreach (Faq::topics() as $topic) {
            $response->assertSee($topic['title']);
        }
    }

    public function test_topics_are_well_formed(): void
    {
        $topics = Faq::topics();
        $ids = array_column($topics, 'id');

        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertGreaterThanOrEqual(7, count($topics));

        foreach ($topics as $topic) {
            $this->assertNotEmpty($topic['title']);
            $this->assertNotEmpty($topic['keywords']);
            $this->assertNotEmpty($topic['answer']);
            $this->assertIsInt($topic['priority']);
        }
    }

    public function test_answers_follow_the_config_numbers(): void
    {
        config([
            'zk.pax_caps.transient' => 9,
            'zk.pax_caps.room' => 14,
            'zk.pax_caps.venue_hall' => 40,
            'zk.hold_hours' => 12,
            'zk.hold_reminder_hours' => 3,
            'zk.notice_days' => 3,
        ]);

        $this->get(route('faq'))
            ->assertSee('Transient: up to 9 guests')
            ->assertSee('up to 14 guests')
            ->assertSee('Venue Hall: up to 40 guests')
            ->assertSee('held for you for 12 hours')
            ->assertSee('about 3 hours before')
            ->assertSee('at least 3 days before');
    }

    public function test_default_caps_match_the_system_context(): void
    {
        $this->get(route('faq'))
            ->assertSee('Pool: no guest limit')
            ->assertSee('Transient: up to 7 guests')
            ->assertSee('up to 12 guests')
            ->assertSee('no guest limit has been set yet');
    }

    public function test_the_earliest_booking_day_follows_config(): void
    {
        $this->get(route('faq'))->assertSee('Online booking starts tomorrow');

        config(['zk.min_advance_days' => 0]);
        $this->get(route('faq'))->assertSee('Online booking starts today');
    }

    public function test_contact_and_gcash_stay_generic_until_configured(): void
    {
        $this->get(route('faq'))
            ->assertSee('Please contact the resort directly.')
            ->assertSee('Rates and packages are not listed in this system.')
            ->assertDontSee('0917');

        config(['zk.contact' => '0917 000 0000', 'zk.gcash' => '0999 111 2222']);

        $this->get(route('faq'))
            ->assertSee('Please contact the resort: 0917 000 0000')
            ->assertSee('to 0999 111 2222.');
    }

    public function test_the_chat_button_is_on_every_kind_of_page(): void
    {
        foreach (['home', 'availability', 'faq', 'login', 'register'] as $route) {
            $this->get(route($route))->assertSee('data-chat-widget', false)->assertSee('Ask a question');
        }

        $this->actingAs(User::factory()->create())->get(route('availability'))->assertSee('data-chat-widget', false);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertSee('data-chat-widget', false);
    }

    public function test_the_chat_loads_its_answers_from_a_public_json_endpoint(): void
    {
        $this->getJson(route('chat.faq'))
            ->assertOk()
            ->assertJsonCount(count(Faq::topics()), 'topics')
            ->assertJsonStructure(['topics' => [['id', 'title', 'keywords', 'priority', 'answer']], 'intro', 'fallback']);
    }

    public function test_the_faq_text_is_not_embedded_in_every_page(): void
    {
        $html = $this->get(route('home'))->getContent();

        $this->assertStringContainsString('data-chat-url="'.route('chat.faq').'"', $html);
        $this->assertStringNotContainsString('id="chat-faq"', $html);
        $this->assertStringNotContainsString('Rates and packages', $html);
    }

    public function test_the_endpoint_carries_no_guest_data(): void
    {
        $guest = User::factory()->create(['name' => 'Zed Uniqueguest', 'email' => 'zed.unique@example.com']);

        $json = $this->actingAs($guest)->getJson(route('chat.faq'))->getContent();

        $this->assertStringNotContainsString('Zed Uniqueguest', $json);
        $this->assertStringNotContainsString('zed.unique@example.com', $json);
    }

    public function test_the_page_carries_no_guest_data(): void
    {
        $guest = User::factory()->create(['name' => 'Zed Uniqueguest', 'email' => 'zed.unique@example.com', 'phone' => '09170001111']);

        $html = $this->actingAs($guest)->get(route('faq'))->getContent();

        foreach (['Zed Uniqueguest', 'zed.unique@example.com', '09170001111'] as $private) {
            $this->assertStringNotContainsString($private, $html);
        }
    }

    public function test_the_footer_links_to_the_faq(): void
    {
        $this->get(route('home'))->assertSee(route('faq'), false);
    }
}
