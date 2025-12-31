# Syncing Branches from Upstream Repository

## Overview

This document explains how to sync all branches from the original repository (upstream) to this fork.

## Background

This repository (`InfraS-SARL/Easya-dolibarr`) is a fork of the original repository `Easya-Solutions/dolibarr`. 

The original repository has **403 branches** that contain important features, fixes, and different versions of the project. To keep this fork up-to-date and have access to all the branches from the original repository, we need to sync them.

## What Has Been Done

1. ✅ Added the upstream remote configuration pointing to `https://github.com/Easya-Solutions/dolibarr.git`
2. ✅ Fetched all branches from the upstream repository
3. ✅ Created a script (`sync-upstream-branches.sh`) to automate the syncing process

## What Needs to Be Done

The branches need to be pushed to this fork. Due to authentication requirements, this needs to be done with appropriate GitHub credentials.

## How to Sync the Branches

### Method 1: Using the Provided Script (Recommended)

Run the sync script:

```bash
./sync-upstream-branches.sh
```

This script will:
- Verify the upstream remote is configured
- Fetch the latest branches from upstream  
- Push all upstream branches to this fork
- Provide progress updates and a summary

### Method 2: Manual Sync

If you prefer to sync manually or need to sync specific branches:

1. **Add upstream remote** (if not already done):
   ```bash
   git remote add upstream https://github.com/Easya-Solutions/dolibarr.git
   ```

2. **Fetch all upstream branches**:
   ```bash
   git fetch upstream --no-tags
   ```

3. **Push all branches to origin**:
   ```bash
   git branch -r | grep "upstream/" | sed 's|  upstream/||' | while read branch; do
       echo "Pushing $branch..."
       git push origin "upstream/$branch:refs/heads/$branch"
   done
   ```

### Method 3: Using GitHub CLI

If you have GitHub CLI installed and authenticated:

```bash
# Fetch from upstream
git fetch upstream --no-tags

# Push each branch
git branch -r | grep "upstream/" | sed 's|  upstream/||' | while read branch; do
    gh api repos/InfraS-SARL/Easya-dolibarr/git/refs \
        -f ref="refs/heads/$branch" \
        -f sha="$(git rev-parse upstream/$branch)"
done
```

## List of Branches to Sync

A total of **403 branches** need to be synced, including:

### Version Branches
- 2.8, 2.9, 3.0, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9
- 4.0, 5.0, 6.0, 7.0, 8.0, 9.0, 10.0, 10.5, 11.0, 12.0, 13.0
- 14.0, 15.0, 16.0, 17.0, 18.0, 18.0.7_lts
- 20.0, 21.0, 2022.x versions, 2024 versions

### Feature and Fix Branches
- develop (main development branch)
- Various fix branches for security issues (CVE fixes)
- Feature branches for new functionality
- Bug fix branches for specific issues
- And many more...

See `/tmp/branches_to_push_clean.txt` for the complete list.

## Verification

After running the sync script, verify that all branches have been pushed:

```bash
# Count branches in origin
git ls-remote --heads origin | wc -l

# Or check on GitHub
# Go to: https://github.com/InfraS-SARL/Easya-dolibarr/branches
```

You should see approximately 403+ branches (plus any new branches created in this fork).

## Troubleshooting

### Authentication Issues

If you encounter authentication errors:
- Make sure you have push access to the repository
- Ensure your GitHub credentials are properly configured
- Consider using SSH instead of HTTPS: `git remote set-url origin git@github.com:InfraS-SARL/Easya-dolibarr.git`

### Rate Limiting

If you hit GitHub's rate limits:
- Wait a few minutes and re-run the script
- The script is idempotent and will skip branches that are already synced

### Large Number of Branches

Syncing 403 branches may take some time (typically 5-15 minutes depending on network speed and server response times).

## Keeping Branches Updated

To keep the branches synchronized with the upstream repository in the future:

```bash
# Fetch latest from upstream
git fetch upstream --no-tags

# Re-run the sync script
./sync-upstream-branches.sh
```

Or set up a GitHub Action to automatically sync branches periodically.

## Notes

- The upstream remote is configured to use HTTPS
- Branches are pushed as-is from upstream without any modifications
- This preserves the complete history and all commits from the original repository
- Protected branches may require additional permissions to push

## Support

If you encounter issues or need help:
1. Check that you have the necessary permissions in the repository
2. Verify your GitHub authentication is working
3. Check the GitHub repository settings for any branch protection rules
4. Review the error messages from the sync script for specific issues
