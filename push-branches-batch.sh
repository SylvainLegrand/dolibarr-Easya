#!/bin/bash
#
# Push all upstream branches to origin in batches
# This script uses git push with multiple refspecs for efficiency
#

set -e

BATCH_SIZE=50
BRANCHES_FILE="/tmp/branches_to_push_clean.txt"

if [ ! -f "$BRANCHES_FILE" ]; then
    echo "Error: Branches file not found: $BRANCHES_FILE"
    echo "Please run the fetch process first."
    exit 1
fi

TOTAL=$(wc -l < "$BRANCHES_FILE")
echo "Total branches to push: $TOTAL"
echo "Batch size: $BATCH_SIZE"
echo ""

batch_num=0
pushed=0
failed=0

# Process branches in batches
while IFS= read -r branch; do
    # Collect branches for this batch
    refspecs=""
    batch_count=0
    
    while [ $batch_count -lt $BATCH_SIZE ] && IFS= read -r branch; do
        refspecs="$refspecs upstream/$branch:refs/heads/$branch"
        batch_count=$((batch_count + 1))
        pushed=$((pushed + 1))
    done <<< "$branch$(cat)"
    
    batch_num=$((batch_num + 1))
    echo "[$batch_num] Pushing batch of $batch_count branches..."
    
    # Push this batch
    if git push origin $refspecs; then
        echo "  ✓ Batch $batch_num successful"
    else
        echo "  ✗ Batch $batch_num failed"
        failed=$((failed + batch_count))
    fi
    
done < "$BRANCHES_FILE"

echo ""
echo "Summary:"
echo "  Total: $TOTAL"
echo "  Pushed: $pushed"
echo "  Failed: $failed"
