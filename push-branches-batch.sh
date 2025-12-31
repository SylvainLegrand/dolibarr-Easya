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
refspecs=()
batch_count=0

while IFS= read -r branch; do
    refspecs+=("upstream/$branch:refs/heads/$branch")
    batch_count=$((batch_count + 1))
    
    # When batch is full, push it
    if [ $batch_count -eq $BATCH_SIZE ]; then
        batch_num=$((batch_num + 1))
        echo "[$batch_num] Pushing batch of $batch_count branches..."
        
        if git push origin "${refspecs[@]}"; then
            echo "  ✓ Batch $batch_num successful"
            pushed=$((pushed + batch_count))
        else
            echo "  ✗ Batch $batch_num failed"
            failed=$((failed + batch_count))
        fi
        
        # Reset for next batch
        refspecs=()
        batch_count=0
    fi
done < "$BRANCHES_FILE"

# Push any remaining branches in the last partial batch
if [ ${#refspecs[@]} -gt 0 ]; then
    batch_num=$((batch_num + 1))
    echo "[$batch_num] Pushing final batch of ${#refspecs[@]} branches..."
    
    if git push origin "${refspecs[@]}"; then
        echo "  ✓ Batch $batch_num successful"
        pushed=$((pushed + ${#refspecs[@]}))
    else
        echo "  ✗ Batch $batch_num failed"
        failed=$((failed + ${#refspecs[@]}))
    fi
fi

echo ""
echo "Summary:"
echo "  Total: $TOTAL"
echo "  Pushed: $pushed"
echo "  Failed: $failed"

