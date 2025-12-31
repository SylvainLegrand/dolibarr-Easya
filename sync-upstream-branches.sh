#!/bin/bash
#
# Script to sync all branches from the upstream repository (Easya-Solutions/dolibarr)
# to the fork (InfraS-SARL/Easya-dolibarr)
#
# This script will:
# 1. Ensure the upstream remote is configured
# 2. Fetch all branches from upstream
# 3. Push all upstream branches to origin (the fork)
#

set -e

echo "========================================="
echo "Syncing branches from upstream to fork"
echo "========================================="

# Repository URLs
UPSTREAM_URL="https://github.com/Easya-Solutions/dolibarr.git"
ORIGIN_REPO="InfraS-SARL/Easya-dolibarr"

# Check if we're in a git repository
if [ ! -d .git ]; then
    echo "Error: Not in a git repository"
    exit 1
fi

echo ""
echo "Step 1: Configuring upstream remote..."
# Add upstream remote if it doesn't exist
if ! git remote get-url upstream &>/dev/null; then
    echo "Adding upstream remote: $UPSTREAM_URL"
    git remote add upstream "$UPSTREAM_URL"
else
    echo "Upstream remote already exists"
    git remote set-url upstream "$UPSTREAM_URL"
fi

echo ""
echo "Step 2: Fetching all branches from upstream..."
git fetch upstream --no-tags

echo ""
echo "Step 3: Getting list of branches to sync..."
# Get list of all upstream branches
git branch -r | grep "upstream/" | sed 's|^[[:space:]]*upstream/||' | sort > "/tmp/upstream_branches_$$.txt"

# Get list of all origin branches  
git branch -r | grep "origin/" | sed 's|^[[:space:]]*origin/||' | sort > "/tmp/origin_branches_$$.txt"

# Find branches that need to be pushed
comm -13 "/tmp/origin_branches_$$.txt" "/tmp/upstream_branches_$$.txt" > "/tmp/branches_to_push_$$.txt"

TOTAL_BRANCHES=$(wc -l < "/tmp/branches_to_push_$$.txt")
echo "Found $TOTAL_BRANCHES branches to sync"

if [ "$TOTAL_BRANCHES" -eq 0 ]; then
    echo "All branches are already in sync!"
    # Clean up temporary files before exit
    rm -f "/tmp/upstream_branches_$$.txt" "/tmp/origin_branches_$$.txt" "/tmp/branches_to_push_$$.txt"
    exit 0
fi

echo ""
echo "Step 4: Pushing branches to origin..."
echo "This may take several minutes..."

count=0
failed=0
succeeded=0
BATCH_SIZE=50

# Process branches in batches for better performance
echo "Using batch mode with $BATCH_SIZE branches per batch..."

current_batch=()
while IFS= read -r branch; do
    current_batch+=("$branch")
    count=$((count + 1))
    
    # When batch is full or we've reached the end, push the batch
    if [ ${#current_batch[@]} -eq $BATCH_SIZE ] || [ $count -eq $TOTAL_BRANCHES ]; then
        batch_num=$(( (count + BATCH_SIZE - 1) / BATCH_SIZE ))
        echo "Pushing batch $batch_num (${#current_batch[@]} branches)..."
        
        # Build refspecs for this batch
        refspecs=()
        for b in "${current_batch[@]}"; do
            refspecs+=("upstream/$b:refs/heads/$b")
        done
        
        # Push the batch
        if git push origin "${refspecs[@]}"; then
            succeeded=$((succeeded + ${#current_batch[@]}))
            echo "  ✓ Batch $batch_num successful (${#current_batch[@]} branches)"
        else
            failed=$((failed + ${#current_batch[@]}))
            echo "  ✗ Batch $batch_num failed"
        fi
        
        # Reset batch
        current_batch=()
        
        # Small delay to avoid rate limiting
        if [ $count -lt $TOTAL_BRANCHES ]; then
            sleep 1
        fi
    fi
done < "/tmp/branches_to_push_$$.txt"

# Clean up temporary files
rm -f "/tmp/upstream_branches_$$.txt" "/tmp/origin_branches_$$.txt" "/tmp/branches_to_push_$$.txt"

echo ""
echo "========================================="
echo "Sync completed!"
echo "========================================="
echo "Total branches: $TOTAL_BRANCHES"
echo "Successfully pushed: $succeeded"
echo "Failed: $failed"
echo ""

if [ $failed -gt 0 ]; then
    echo "Some branches failed to push. This might be due to:"
    echo "  - Authentication issues"
    echo "  - Rate limiting"
    echo "  - Network issues"
    echo ""
    echo "You can re-run this script to retry pushing the failed branches."
    exit 1
fi

echo "All branches have been successfully synced!"
