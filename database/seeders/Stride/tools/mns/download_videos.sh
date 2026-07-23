#!/usr/bin/env bash
#
# Stage 3: download each exercise's demo video (YouTube or Vimeo embed) as
# videos/<slug>.mp4 via yt-dlp. Vimeo embeds are domain-locked, so those are
# fetched with the muscleandstrength.com referer.
# Resumable — already-downloaded slugs are skipped, so it can run across sessions.
# Requires: brew install yt-dlp jq ffmpeg
#
# Usage: ./download_videos.sh [max_count] [vimeo|youtube|all]
#   max_count — optional cap of download ATTEMPTS this run (0 = all remaining).
#   source    — restrict to one video host (default all).
# Env: YTDLP_BIN to override the yt-dlp binary; YTDLP_EXTRA for extra flags
# (e.g. --cookies-from-browser safari, --impersonate chrome).

set -uo pipefail
cd "$(dirname "$0")"
mkdir -p videos

MAX="${1:-0}"
SOURCE="${2:-all}"
BIN="${YTDLP_BIN:-yt-dlp}"
COUNT=0
OK=0
FAILED=0
CONSECUTIVE_FAILS=0

while read -r SLUG YT_ID VIMEO_ID; do
    OUT="videos/${SLUG}.mp4"
    [[ -s "$OUT" ]] && continue

    if [[ "$VIMEO_ID" != "-" ]]; then
        [[ "$SOURCE" == "youtube" ]] && continue
        URL="https://player.vimeo.com/video/${VIMEO_ID}"
        REFERER="https://www.muscleandstrength.com/"
    else
        [[ "$SOURCE" == "vimeo" ]] && continue
        URL="https://www.youtube.com/watch?v=${YT_ID}"
        REFERER=""
    fi

    if [[ "$MAX" -gt 0 && "$COUNT" -ge "$MAX" ]]; then
        echo "Reached cap of $MAX attempts this run."
        break
    fi
    COUNT=$((COUNT + 1))

    echo "[$COUNT] $SLUG  ($URL)"
    GOT=0
    for ATTEMPT in 1 2 3; do
        # shellcheck disable=SC2086 — YTDLP_EXTRA is intentionally word-split
        if "$BIN" ${YTDLP_EXTRA:-} \
            ${REFERER:+--referer "$REFERER"} \
            -f "bv*[height<=720]+ba/bv*[height<=720]/b[height<=720]/b" \
            --merge-output-format mp4 \
            --socket-timeout 20 \
            --no-progress \
            -o "$OUT" \
            "$URL"; then
            GOT=1
            break
        fi
        rm -f "$OUT" videos/"${SLUG}".f*.mp4 videos/"${SLUG}".*.part 2>/dev/null
        echo "  .. attempt $ATTEMPT failed, retrying"
        sleep 12
    done

    if [[ "$GOT" -eq 1 ]]; then
        OK=$((OK + 1))
        CONSECUTIVE_FAILS=0
    else
        FAILED=$((FAILED + 1))
        CONSECUTIVE_FAILS=$((CONSECUTIVE_FAILS + 1))
        echo "  !! failed after 3 attempts: $SLUG"
        if [[ "$CONSECUTIVE_FAILS" -ge 10 ]]; then
            echo "Aborting: $CONSECUTIVE_FAILS slugs failed 3x in a row (host blocked or offline?)."
            break
        fi
    fi
    sleep "$((RANDOM % 4 + 3))"
done < <(jq -r '.[] | select(.youtube_id != null or .vimeo_id != null)
                | "\(.slug) \(.youtube_id // "-") \(.vimeo_id // "-")"' scraped.json)

echo "Run finished: $OK ok, $FAILED failed. $(ls videos/*.mp4 2>/dev/null | wc -l | tr -d ' ') videos on disk."
