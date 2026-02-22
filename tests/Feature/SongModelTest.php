<?php

namespace Tests\Feature;

use App\Models\Songs\Song;
use App\Models\Songs\SongArtist;
use App\Models\Songs\SongGenre;
use App\Models\Songs\SongTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_song_can_be_created(): void
    {
        $song = Song::factory()->create([
            'title' => 'Hallelujah',
            'number' => 1,
        ]);

        $this->assertDatabaseHas('songs', [
            'id' => $song->id,
            'title' => 'Hallelujah',
            'number' => 1,
        ]);
    }

    public function test_song_belongs_to_genre(): void
    {
        $genre = SongGenre::factory()->create(['name' => 'Worship']);
        $song = Song::factory()->create(['genre_id' => $genre->id]);

        $this->assertEquals('Worship', $song->genre->name);
    }

    public function test_song_can_have_null_genre(): void
    {
        $song = Song::factory()->create(['genre_id' => null]);

        $this->assertNull($song->genre);
    }

    public function test_song_has_many_artists(): void
    {
        $song = Song::factory()->create();
        $artist1 = SongArtist::factory()->create(['name' => 'Chris Tomlin']);
        $artist2 = SongArtist::factory()->create(['name' => 'Matt Redman']);

        $song->artists()->attach([$artist1->id, $artist2->id]);

        $this->assertCount(2, $song->artists);
        $this->assertTrue($song->artists->contains($artist1));
        $this->assertTrue($song->artists->contains($artist2));
    }

    public function test_song_has_many_tags(): void
    {
        $song = Song::factory()->create();
        $tag1 = SongTag::factory()->create(['name' => 'Slow']);
        $tag2 = SongTag::factory()->create(['name' => 'Acoustic']);

        $song->tags()->attach([$tag1->id, $tag2->id]);

        $this->assertCount(2, $song->tags);
        $this->assertTrue($song->tags->contains($tag1));
        $this->assertTrue($song->tags->contains($tag2));
    }

    public function test_artist_has_many_songs(): void
    {
        $artist = SongArtist::factory()->create();
        $song1 = Song::factory()->create();
        $song2 = Song::factory()->create();

        $artist->songs()->attach([$song1->id, $song2->id]);

        $this->assertCount(2, $artist->songs);
    }

    public function test_genre_has_many_songs(): void
    {
        $genre = SongGenre::factory()->create();
        Song::factory()->count(3)->create(['genre_id' => $genre->id]);

        $this->assertCount(3, $genre->songs);
    }

    public function test_tag_has_many_songs(): void
    {
        $tag = SongTag::factory()->create();
        $songs = Song::factory()->count(2)->create();

        $tag->songs()->attach($songs->pluck('id'));

        $this->assertCount(2, $tag->songs);
    }

    public function test_deleting_genre_nulls_song_genre_id(): void
    {
        $genre = SongGenre::factory()->create();
        $song = Song::factory()->create(['genre_id' => $genre->id]);

        $genre->delete();

        $song->refresh();
        $this->assertNull($song->genre_id);
    }

    public function test_deleting_song_detaches_artists(): void
    {
        $song = Song::factory()->create();
        $artist = SongArtist::factory()->create();
        $song->artists()->attach($artist->id);

        $song->delete();

        $this->assertDatabaseMissing('song_artist_song_pivot', [
            'song_id' => $song->id,
        ]);
    }

    public function test_deleting_song_detaches_tags(): void
    {
        $song = Song::factory()->create();
        $tag = SongTag::factory()->create();
        $song->tags()->attach($tag->id);

        $song->delete();

        $this->assertDatabaseMissing('song_tag_song_pivot', [
            'song_id' => $song->id,
        ]);
    }

    public function test_artist_name_is_unique(): void
    {
        SongArtist::factory()->create(['name' => 'Unique Artist']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SongArtist::factory()->create(['name' => 'Unique Artist']);
    }

    public function test_tag_name_is_unique(): void
    {
        SongTag::factory()->create(['name' => 'Unique Tag']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SongTag::factory()->create(['name' => 'Unique Tag']);
    }

    public function test_song_can_store_bpm_as_string(): void
    {
        $song = Song::factory()->create(['bpm' => '120-140']);

        $this->assertEquals('120-140', $song->fresh()->bpm);
    }

    public function test_song_can_have_null_bpm(): void
    {
        $song = Song::factory()->create(['bpm' => null]);

        $this->assertNull($song->fresh()->bpm);
    }

    public function test_song_can_store_lyrics_with_html(): void
    {
        $lyrics = '<p><b>Verse 1</b></p><p>Amazing grace, how sweet the sound</p>';
        $song = Song::factory()->create(['lyrics' => $lyrics]);

        $this->assertEquals($lyrics, $song->fresh()->lyrics);
    }
}
