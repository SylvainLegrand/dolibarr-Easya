#!/bin/bash
#
# Push all upstream branches to origin in batches
# This script uses git push with multiple refspecs for efficiency
#
# Usage: 
#   ./push-branches-batch.sh [branches_file]
#   cat branches_list.txt | ./push-branches-batch.sh
#
#   branches_file: Path to file containing branch names (optional)
#   If no file is provided, reads from stdin
#

set -e

BATCH_SIZE=50

# Determine input source and set up cleanup
CLEANUP_FILE=""

if [ -n "$1" ]; then
    BRANCHES_FILE="$1"
    if [ ! -f "$BRANCHES_FILE" ]; then
        echo "Error: Branches file not found: $BRANCHES_FILE"
        echo "Usage: $0 [branches_file]"
        echo "       cat branches_list.txt | $0"
        exit 1
    fi
    INPUT_SOURCE="$BRANCHES_FILE"
else
    # Read from stdin into a secure temp file
    BRANCHES_FILE=$(mktemp)
    CLEANUP_FILE="$BRANCHES_FILE"
    trap "rm -f '$CLEANUP_FILE'" EXIT
    cat > "$BRANCHES_FILE"
    INPUT_SOURCE="stdin"
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

# Exit with appropriate code
if [ $failed -eq 0 ]; then
    exit 0
else
    exit 1
fi

