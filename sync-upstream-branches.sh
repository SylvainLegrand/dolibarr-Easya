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
git branch -r | grep "upstream/" | sed 's|  upstream/||' | sed 's/^[[:space:]]*//' | sort > /tmp/upstream_branches.txt

# Get list of all origin branches  
git branch -r | grep "origin/" | sed 's|  origin/||' | sed 's/^[[:space:]]*//' | sort > /tmp/origin_branches.txt

# Find branches that need to be pushed
comm -13 /tmp/origin_branches.txt /tmp/upstream_branches.txt > /tmp/branches_to_push.txt

TOTAL_BRANCHES=$(wc -l < /tmp/branches_to_push.txt)
echo "Found $TOTAL_BRANCHES branches to sync"

if [ "$TOTAL_BRANCHES" -eq 0 ]; then
    echo "All branches are already in sync!"
    exit 0
fi

echo ""
echo "Step 4: Pushing branches to origin..."
echo "This may take several minutes..."

count=0
failed=0
succeeded=0

while IFS= read -r branch; do
    count=$((count + 1))
    
    # Show progress every 10 branches
    if [ $((count % 10)) -eq 0 ]; then
        echo "Progress: $count/$TOTAL_BRANCHES branches processed (succeeded: $succeeded, failed: $failed)"
    fi
    
    # Push the branch
    if git push origin "upstream/$branch:refs/heads/$branch" 2>&1 | grep -q "fatal"; then
        failed=$((failed + 1))
        echo "  [FAILED] $branch"
    else
        succeeded=$((succeeded + 1))
    fi
done < /tmp/branches_to_push.txt

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
