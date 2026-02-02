#!/bin/bash

# Space-Utils Standards Setup for Claude Code
# Configures Claude to follow PHP/Space-Utils coding standards

set -euo pipefail

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Setting up Space-Utils coding standards for Claude Code...${NC}"

# Make feedback script executable
if [[ -f ".claude/hooks/php-paradigm/standards-feedback.sh" ]]; then
    chmod +x .claude/hooks/php-paradigm/standards-feedback.sh
    echo -e "${GREEN}✓${NC} Standards feedback hook configured"
fi

# Verify CLAUDE.md has standards section
if grep -q "PHP/Space-Utils Coding Standards" CLAUDE.md; then
    echo -e "${GREEN}✓${NC} CLAUDE.md already contains standards reference"
else
    echo -e "${YELLOW}!${NC} Please run claude-merge to update CLAUDE.md with standards"
fi

# Check if standards path exists (use environment variable with fallback)
STANDARDS_PATH="${SPACE_UTILS_PATH:-}/coding-standards"
if [[ -z "${SPACE_UTILS_PATH:-}" ]]; then
    echo -e "${YELLOW}!${NC} SPACE_UTILS_PATH environment variable not set"
    echo -e "  Please set it: export SPACE_UTILS_PATH=/path/to/space-utils"
elif [[ -d "$STANDARDS_PATH" ]]; then
    echo -e "${GREEN}✓${NC} Standards directory found at: $STANDARDS_PATH"
    echo -e "  Key files:"
    echo -e "  • core-principles/simplicity.md"
    echo -e "  • language-features/strong-typing-standards.md"
    echo -e "  • tools/auto-fixer.php"
else
    echo -e "${YELLOW}!${NC} Standards directory not found at: $STANDARDS_PATH"
    echo -e "  Please ensure space-utils is installed at the expected location"
fi

echo -e "\n${GREEN}Setup complete!${NC}"
echo -e "Claude will now:"
echo -e "• Check standards before writing PHP code"
echo -e "• Provide feedback when standards are violated"
echo -e "• Suggest Space-Utils functions when appropriate"
echo -e "\nUse ${GREEN}/checkstandards${NC} command for quick reference"