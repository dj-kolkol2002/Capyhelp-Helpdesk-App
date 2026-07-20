<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TicketAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_can_change_ticket_reply_tone(): void
    {
        config([
            'services.ollama.base_url' => 'http://ollama.test',
            'services.ollama.model' => 'test-model',
            'services.ollama.fallback_model' => 'fallback-model',
            'services.ollama.timeout' => 180,
            'services.ollama.temperature' => 0.0,
            'services.ollama.top_p' => 0.2,
            'services.ollama.top_k' => 10,
            'services.ollama.repeat_penalty' => 1.2,
            'services.ollama.num_ctx' => 2048,
            'services.ollama.num_predict' => 450,
            'services.ollama.seed' => 42,
        ]);

        Http::fake([
            'ollama.test/api/generate' => Http::response([
                'response' => 'Dzień dobry, chętnie pomożemy rozwiązać tę sprawę.',
            ]),
        ]);

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create(['assignee' => $agent->id]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/tone", [
            'message' => 'naprawimy to',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Dzień dobry, chętnie pomożemy rozwiązać tę sprawę.');

        Http::assertSent(fn ($request): bool => $request->url() === 'http://ollama.test/api/generate'
            && $request['model'] === 'test-model'
            && $request['options']['temperature'] === 0.0
            && $request['options']['top_p'] === 0.2
            && $request['options']['top_k'] === 10
            && $request['options']['repeat_penalty'] === 1.2
            && $request['options']['num_ctx'] === 2048
            && $request['options']['num_predict'] === 450
            && $request['options']['seed'] === 42
            && str_contains($request['system'], 'bez wymyślania faktów')
            && str_contains($request['prompt'], '<wiadomosc_agenta>')
            && str_contains($request['prompt'], 'Pisz naturalnie')
            && str_contains($request['prompt'], 'naprawimy to')
            && str_contains($request['prompt'], $ticket->number));
    }

    public function test_ai_tone_uses_fallback_model_when_primary_model_fails(): void
    {
        config([
            'services.ollama.base_url' => 'http://ollama.test',
            'services.ollama.model' => 'large-model',
            'services.ollama.fallback_model' => 'small-model',
        ]);

        Http::fakeSequence('ollama.test/api/generate')
            ->push(['error' => 'model does not fit in memory'], 500)
            ->push(['response' => 'Dzień dobry, poczta została zresetowana.'], 200);

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create(['assignee' => $agent->id]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/tone", [
            'message' => 'Poczta została zresetowana',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Dzień dobry, poczta została zresetowana.');

        Http::assertSentCount(2);

        $requests = Http::recorded()->map(fn ($record) => $record[0]);

        $this->assertSame('large-model', $requests[0]['model']);
        $this->assertSame('small-model', $requests[1]['model']);
    }

    public function test_ai_tone_uses_safe_fallback_when_model_adds_facts(): void
    {
        config([
            'services.ollama.base_url' => 'http://ollama.test',
            'services.ollama.model' => 'test-model',
        ]);

        Http::fake([
            'ollama.test/api/generate' => Http::response([
                'response' => 'Przepraszamy, sprawdzimy awarię dzisiaj o 15:00.',
            ]),
        ]);

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create(['assignee' => $agent->id]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/tone", [
            'message' => 'problem z logowaniem',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('guarded', true)
            ->assertJsonPath('message', "Dzień dobry,\n\nproblem z logowaniem.");
    }

    public function test_ai_tone_removes_customer_message_heading(): void
    {
        config([
            'services.ollama.base_url' => 'http://ollama.test',
            'services.ollama.model' => 'test-model',
        ]);

        Http::fake([
            'ollama.test/api/generate' => Http::response([
                'response' => "**Wiadomość dla klienta**\n\nDzień dobry, zgłoszenie zostało przyjęte.",
            ]),
        ]);

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create(['assignee' => $agent->id]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/tone", [
            'message' => 'Dzień dobry, zgłoszenie zostało przyjęte.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Dzień dobry, zgłoszenie zostało przyjęte.');
    }

    public function test_ai_tone_removes_rewrite_commentary(): void
    {
        config([
            'services.ollama.base_url' => 'http://ollama.test',
            'services.ollama.model' => 'test-model',
        ]);

        Http::fake([
            'ollama.test/api/generate' => Http::response([
                'response' => "Oto przepisane treści w spokojnym, profesjonalnym i życzliwym tonie:\n\nDzień dobry, poczta została zrestartowana.",
            ]),
        ]);

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create(['assignee' => $agent->id]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/tone", [
            'message' => 'Dzień dobry, poczta została zrestartowana.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Dzień dobry, poczta została zrestartowana.');
    }

    public function test_ai_tone_removes_ticket_metadata_suffix(): void
    {
        config([
            'services.ollama.base_url' => 'http://ollama.test',
            'services.ollama.model' => 'test-model',
        ]);

        Http::fake([
            'ollama.test/api/generate' => Http::response([
                'response' => "Pozdrawiam,\nPoczątek sesji został zrestartowany.\n\n- Numer: TKT-000024\n- Temat: Integracja czatu nie odpowiada\n- Priorytet: medium\n- Status: resolved",
            ]),
        ]);

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create([
            'assignee' => $agent->id,
            'number' => 'TKT-000024',
            'subject' => 'Integracja czatu nie odpowiada',
            'priority' => 'medium',
            'status' => 'resolved',
        ]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/tone", [
            'message' => 'Pozdrawiam, początek sesji został zrestartowany.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', "Pozdrawiam,\nPoczątek sesji został zrestartowany.");
    }

    public function test_ai_tone_removes_robotic_system_perspective(): void
    {
        config([
            'services.ollama.base_url' => 'http://ollama.test',
            'services.ollama.model' => 'test-model',
        ]);

        Http::fake([
            'ollama.test/api/generate' => Http::response([
                'response' => "Pozdrawiam klienta,\nPowyższa wiadomość jest informacją o zakończeniu zgłoszenia TKT-000024 dotyczącego integracji czatu.",
            ]),
        ]);

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create([
            'assignee' => $agent->id,
            'number' => 'TKT-000024',
            'subject' => 'Integracja czatu nie odpowiada',
        ]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/tone", [
            'message' => 'Początek sesji został zrestartowany, poczta powinna działać poprawnie.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('guarded', true)
            ->assertJsonPath('message', "Dzień dobry,\n\nPoczątek sesji został zrestartowany, poczta powinna działać poprawnie.");
    }

    public function test_ai_can_summarize_closed_ticket(): void
    {
        config([
            'services.ollama.base_url' => 'http://ollama.test',
            'services.ollama.model' => 'test-model',
        ]);

        Http::fake([
            'ollama.test/api/generate' => Http::response([
                'response' => "[DANE] Klient zgłosił problem z fakturą.\n[M1] Faktura została poprawiona.\n[DANE] Sprawa została zamknięta.",
            ]),
        ]);

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create([
            'assignee' => $agent->id,
            'status' => 'closed',
            'subject' => 'Problem z fakturą',
        ]);
        $ticket->messages()->create([
            'user_id' => $agent->id,
            'author_name' => $agent->name,
            'author_email' => $agent->email,
            'author_type' => 'agent',
            'body' => 'Faktura została poprawiona.',
        ]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/summary");

        $response
            ->assertOk()
            ->assertJsonPath('summary', "- Faktura została poprawiona.\n- Sprawa została zamknięta.");

        $this->assertSame(
            "- Faktura została poprawiona.\n- Sprawa została zamknięta.",
            $ticket->fresh()->ai_summary
        );

        Http::assertSent(fn ($request): bool => str_contains($request['prompt'], 'Problem z fakturą')
            && str_contains($request['prompt'], 'Faktura została poprawiona.')
            && str_contains($request['prompt'], '<rozmowa>')
            && str_contains($request['prompt'], 'M1 |')
            && str_contains($request['prompt'], 'Brak danych w zgłoszeniu o finalnym rozwiązaniu.'));
    }

    public function test_ai_summary_drops_items_without_valid_sources(): void
    {
        config([
            'services.ollama.base_url' => 'http://ollama.test',
            'services.ollama.model' => 'test-model',
        ]);

        Http::fake([
            'ollama.test/api/generate' => Http::response([
                'response' => "[M1] Faktura została poprawiona.\n[M99] Usunęliśmy awarię bazy danych.\nBez źródła: klient otrzymał rabat.",
            ]),
        ]);

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create([
            'assignee' => $agent->id,
            'status' => 'closed',
            'subject' => 'Problem z fakturą',
        ]);
        $ticket->messages()->create([
            'user_id' => $agent->id,
            'author_name' => $agent->name,
            'author_email' => $agent->email,
            'author_type' => 'agent',
            'body' => 'Faktura została poprawiona.',
        ]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/summary");

        $response
            ->assertOk()
            ->assertJsonPath('summary', '- Faktura została poprawiona.');
    }

    public function test_ai_summary_requires_finished_ticket(): void
    {
        Http::fake();

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create([
            'assignee' => $agent->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($agent)->postJson("/tickets/{$ticket->id}/ai/summary");

        $response->assertUnprocessable();
        Http::assertNothingSent();
    }
}
