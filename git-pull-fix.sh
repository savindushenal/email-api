#!/bin/bash

# Git Pull Resolution Script for Production Server

echo "=== Resolving Git Pull Conflict ==="
echo ""

# Option 1: Stash local changes and pull (RECOMMENDED)
echo "Option 1: Stash local changes (keeps them safely)"
echo "Run these commands:"
echo "  git stash"
echo "  git pull origin main"
echo "  git stash pop  # (optional: if you need the local changes back)"
echo ""

# Option 2: Discard local changes and force pull
echo "Option 2: Discard local changes (cache files are auto-generated)"
echo "Run these commands:"
echo "  git checkout -- bootstrap/cache/services.php"
echo "  git pull origin main"
echo ""

# Option 3: Commit local changes first
echo "Option 3: Commit local changes first"
echo "Run these commands:"
echo "  git add bootstrap/cache/services.php"
echo "  git commit -m 'Update cached services'"
echo "  git pull origin main --rebase"
echo ""

echo "=== RECOMMENDED SOLUTION (Option 1) ==="
echo "Since bootstrap/cache/services.php is a cache file,"
echo "it's safe to stash or discard. Use Option 1 or 2."
echo ""
echo "Quick fix:"
echo "  git stash && git pull origin main"
