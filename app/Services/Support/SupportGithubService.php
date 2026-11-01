<?php

namespace App\Services\Support;

use App\Models\Support\SupportProject;
use App\Models\Support\SupportSyncOperation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SupportGithubService
{
    public function api(SupportProject $project): PendingRequest
    {
        $key = 'support-github:'.config('support.github.app_id').':'.$project->github_installation_id.':'.$project->github_repo.':'.hash('sha256', config('support.github.private_key') ?? '');
        $token = Cache::remember($key, 2700, function () use ($project): string {
            $encode = fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode(['iat' => now()->timestamp - 60, 'exp' => now()->timestamp + 300, 'iss' => config('support.github.app_id')]);
            $privateKey = str_replace('\\n', "\n", config('support.github.private_key') ?? '');
            if (! $privateKey || ! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('GitHub App signing is not configured.');
            }
            $jwt = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $response = Http::acceptJson()->withToken($jwt)->timeout(30)->post('https://api.github.com/app/installations/'.$project->github_installation_id.'/access_tokens', ['repositories' => [explode('/', $project->github_repo)[1]]])->throw();

            return $response->json('token');
        });

        return Http::baseUrl('https://api.github.com')->acceptJson()->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])->withToken($token)->timeout(30);
    }

    public function trusted(SupportProject $project, string $login): bool
    {
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{0,38}(\[bot\])?$/', $login)) {
            return false;
        }

        return Cache::remember('support-github-permission:'.$project->id.':'.$login, 60, function () use ($project, $login): bool {
            $response = $this->api($project)->get('/repos/'.$project->github_repo.'/collaborators/'.$login.'/permission');

            return $response->successful() && in_array($response->json('permission'), ['admin', 'maintain', 'write'], true);
        });
    }

    public function issue(SupportProject $project, int $number): array
    {
        return $this->api($project)->get('/repos/'.$project->github_repo.'/issues/'.$number)->throw()->json();
    }

    public function sync(SupportSyncOperation $operation): void
    {
        $project = $operation->project;
        $request = $operation->request;
        $api = $this->api($project);
        $base = '/repos/'.$project->github_repo.'/issues';
        $marker = '<!-- support:'.$operation->operation_key.' -->';
        if ($operation->kind === 'issue') {
            if ($request->github_issue_number) {
                return;
            }
            $found = $this->findMarker($api, $base, $marker, ['state' => 'all']);
            $issue = $found ?? $api->post($base, ['title' => $operation->payload['title'], 'body' => $operation->payload['body']."\n\n".$marker, 'labels' => ['klient', 'status:nove']])->throw()->json();
            $request->update(['github_issue_number' => $issue['number'], 'github_issue_id' => $issue['id']]);
            $operation->update(['provider_id' => $issue['id']]);

            return;
        }
        $request->refresh();
        if (! $request->github_issue_number) {
            throw new \RuntimeException('The GitHub issue is not synced yet.');
        }
        $path = $base.'/'.$request->github_issue_number;
        if ($operation->kind === 'comment') {
            $found = $this->findMarker($api, $path.'/comments', $marker);
            $comment = $found ?? $api->post($path.'/comments', ['body' => $operation->payload['body']."\n\n".$marker])->throw()->json();
            $operation->update(['provider_id' => $comment['id']]);

            return;
        }
        $issue = $this->issue($project, $request->github_issue_number);
        $labels = array_values(array_filter(array_column($issue['labels'] ?? [], 'name'), fn (string $label): bool => ! str_starts_with($label, 'status:')));
        $mapping = ['new' => 'status:nove', 'queued' => 'status:v-rade', 'in_progress' => 'status:pracuje-sa', 'waiting_client' => 'status:caka-na-klienta', 'quote_ready' => 'status:ponuka', 'ready_for_acceptance' => 'status:hotove'];
        $status = $request->status;
        $labels[] = $mapping[$status] ?? 'status:hotove';
        $api->patch($path, ['labels' => $labels, 'state' => $status === 'accepted' ? 'closed' : 'open'])->throw();
    }

    private function findMarker(PendingRequest $api, string $path, string $marker, array $query = []): ?array
    {
        for ($page = 1; ; $page++) {
            $items = $api->get($path, [...$query, 'per_page' => 100, 'page' => $page])->throw()->json();
            foreach ($items as $item) {
                if (str_contains($item['body'] ?? '', $marker)) {
                    return $item;
                }
            }
            if (count($items) < 100) {
                return null;
            }
        }
    }
}
