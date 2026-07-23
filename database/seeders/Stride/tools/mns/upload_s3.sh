#!/usr/bin/env bash
#
# Stage 4: sync videos/ to the public Hetzner object-storage bucket under
# stride/exercises/mns/ (kept apart from the IG-shortcode tricklist files).
# Requires: brew install awscli, plus credentials in the environment:
#   export AWS_ACCESS_KEY_ID=…  AWS_SECRET_ACCESS_KEY=…   (from the prod .env)
#
# After uploading, rerun build_dataset.py so every uploaded slug gets its
# video_url stamped into data/mns_gym_exercises.json.

set -euo pipefail
cd "$(dirname "$0")"

BUCKET="public-synapps-dashboard"
ENDPOINT="https://nbg1.your-objectstorage.com"

aws s3 sync videos/ "s3://${BUCKET}/stride/exercises/mns/" \
    --endpoint-url "$ENDPOINT" \
    --exclude "*" --include "*.mp4" \
    --content-type video/mp4 \
    --acl public-read

aws s3 sync thumbs/ "s3://${BUCKET}/stride/exercises/mns/thumbs/" \
    --endpoint-url "$ENDPOINT" \
    --exclude "*" --include "*.jpg" \
    --content-type image/jpeg \
    --acl public-read

echo "Synced. Spot-check:"
FIRST=$(ls videos/*.mp4 | head -1 | xargs basename)
echo "  curl -sI https://${BUCKET}.nbg1.your-objectstorage.com/stride/exercises/mns/${FIRST} | head -3"
