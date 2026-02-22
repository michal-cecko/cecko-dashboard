<?php

namespace Tests\Feature;

use App\Models\Songs\Song;
use App\Models\Songs\SongArtist;
use App\Models\Songs\SongGenre;
use App\Models\Songs\SongTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_songs_index_returns_all_songs(): void
    {
        Song::factory()->count(3)->create();

        $response = $this->getJson('/api/songs');

        $response->assertOk();
        $response->assertJsonCount(3);
    }

    public function test_songs_index_includes_artists(): void
    {
        $song = Song::factory()->create();
        $artist = SongArtist::factory()->create(['name' => 'Test Artist']);
        $song->artists()->attach($artist->id);

        $response = $this->getJson('/api/songs');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Test Artist']);
    }

    public function test_songs_index_includes_tags(): void
    {
        $song = Song::factory()->create();
        $tag = SongTag::factory()->create(['name' => 'Praise']);
        $song->tags()->attach($tag->id);

        $response = $this->getJson('/api/songs');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Praise']);
    }

    public function test_songs_index_returns_empty_for_no_songs(): void
    {
        $response = $this->getJson('/api/songs');

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_songs_index_returns_song_fields(): void
    {
        $genre = SongGenre::factory()->create();
        Song::factory()->create([
            'title' => 'Test Song',
            'number' => 42,
            'bpm' => '120',
            'genre_id' => $genre->id,
        ]);

        $response = $this->getJson('/api/songs');

        $response->assertOk();
        $response->assertJsonFragment([
            'title' => 'Test Song',
            'number' => 42,
            'bpm' => '120',
        ]);
    }

    public function test_song_genres_index_returns_all_genres(): void
    {
        SongGenre::factory()->count(3)->create();

        $response = $this->getJson('/api/song-genres');

        $response->assertOk();
    }

    public function test_song_tags_index_returns_all_tags(): void
    {
        SongTag::factory()->count(3)->create();

        $response = $this->getJson('/api/song-tags');

        $response->assertOk();
    }
}
