# Development Partnership

We're building production-quality code together. Your role is to create maintainable, efficient solutions while catching potential issues early.

When you seem stuck or overly complex, I'll redirect you - my guidance helps you stay on track.

## 📘 PHP/Space-Utils Coding Standards

**MANDATORY for all PHP code generation:**

### Standards Hub File
**Entry Point**: `$SPACE_UTILS_PATH/coding-standards/claude.md`

> **Setup**: Set the `SPACE_UTILS_PATH` environment variable to your Space-Utils installation directory.
> Example: `export SPACE_UTILS_PATH=/path/to/space-utils`

### PHP Standards Workflow
1. **FIRST**: Read the hub file `$SPACE_UTILS_PATH/coding-standards/claude.md` for overview and standards index
2. **THEN**: Based on your task, read specific standards referenced in the hub:

| Task Type | Read These Standards (relative to hub) |
|-----------|---------------------------------------|
| Writing new PHP code | `language-features/strong-typing-standards.md`, `language-features/naming-conventions.md` |
| Architecture decisions | `core-principles/simplicity.md`, `core-principles/type-safety.md` |
| Using Space-Utils components | `language-features/native-php-preferences.md` |
| Async/advanced patterns | `language-features/async-safety-patterns.md` |
| Refactoring | `core-principles/functional-programming.md` |

### 🚨 CRITICAL: PHP Analysis Tool Priority

**MANDATORY - Use PHPStan, NOT php -l:**
- **ALWAYS use PHPStan** for syntax checking and static analysis
- **NEVER default to `php -l`** - it only catches basic parse errors
- PHPStan detects: type errors, runtime errors, dead code, anti-patterns, Space-Utils violations
- `php -l` misses: 90% of real issues that break production code including runtime errors

**Correct PHP Verification Sequence:**
```bash
# 1. PHPStan for comprehensive analysis (REQUIRED)
phpstan analyze <file> --level=max --no-progress

# 2. Space-Utils auto-fixer for paradigm compliance
php $SPACE_UTILS_PATH/coding-standards/tools/auto-fixer.php <file>

# 3. php -l ONLY if PHPStan unavailable (emergency fallback)
```

### Before Writing PHP Code - CHECK THESE:
1. **Read the hub file first**: `$SPACE_UTILS_PATH/coding-standards/claude.md`
2. **Then selectively read based on task**:
   - **Core Principles** (from hub index → `core-principles/`)
   - **Language Features** (from hub index → `language-features/`)

3. **Critical Anti-Patterns**: See "HARD OVER-ENGINEERING BLOCKERS" section below

### 🚫 PHP File Structure Constraints

**Strict PSR-4 Enforcement:**

#### Single Class Per File Rule
**MANDATORY**: Each PHP file MUST contain EXACTLY ONE class - NO EXCEPTIONS!

❌ **STRICTLY FORBIDDEN - IMMEDIATE REJECTION:**
```php
// VIOLATION: Multiple classes in single file
<?php
class User {
    // ...
}

class UserRepository {  // ❌ SECOND CLASS = VIOLATION
    // ...
}

interface UserInterface {  // ❌ INTERFACE WITH CLASS = VIOLATION
    // ...
}
```

✅ **REQUIRED STRUCTURE:**
```php
// File: User.php
<?php
class User {
    // Single class only
}

// File: UserRepository.php  
<?php
class UserRepository {
    // Separate file for each class
}

// File: UserInterface.php
<?php
interface UserInterface {
    // Interfaces in their own files
}
```

#### Enforcement Mechanisms
1. **Pre-commit Hook**: Automatically rejects commits with violations
2. **CI/CD Pipeline**: Fails builds containing multiple classes per file
3. **Code Review**: Mandatory rejection of PRs with violations
4. **Auto-fixer**: Splits files automatically when violations detected

#### Violation Detection Patterns
- Multiple `class` declarations in single file
- Mixed `class` and `interface` declarations
- Mixed `class` and `trait` declarations
- Nested class definitions (also forbidden)
- Anonymous classes exceeding single use

#### PSR-4 Compliance Requirements
- **Filename MUST match class name exactly** (case-sensitive)
- **Namespace MUST reflect directory structure**
- **One class = One file = One namespace entry**

#### Automated Validation & Enforcement Tools

**Tools Location:** `templates/tools/php/`

```bash
# Validate single class per file rule
php templates/tools/php/single-class-validator.php <file-or-directory>
php templates/tools/php/single-class-validator.php --json src/  # JSON output
php templates/tools/php/single-class-validator.php -v src/      # Verbose mode

# Auto-split violating files
php templates/tools/php/class-file-splitter.php <file.php>
php templates/tools/php/class-file-splitter.php --dry-run <file.php>  # Preview changes
php templates/tools/php/class-file-splitter.php --output-dir=./split <file.php>
```

#### Multi-Layer Enforcement System

**Layer 1: PreToolUse Hook (BLOCKING)**
- Location: `templates/hooks/pre_tool_use.py`
- Blocks PHP file creation/editing with multiple classes BEFORE the operation
- Exit code 2 = operation blocked, error shown to Claude
- Cannot be bypassed - enforced at tool execution level

**Layer 2: Pre-Commit Hook (BLOCKING)**
- Location: `templates/hooks/pre-commit.sh`
- Validates all staged PHP files before commit
- Blocks commits containing PSR-4 violations
- Shows clear error messages with fix instructions

**Layer 3: Validator Tool (Manual Check)**
- Location: `templates/tools/php/single-class-validator.php`
- Run manually or in CI/CD pipelines
- Supports JSON output for automation
- Recursive directory scanning

**Layer 4: Auto-Splitter Tool (Auto-Fix)**
- Location: `templates/tools/php/class-file-splitter.php`
- Automatically splits multi-class files
- Preserves namespace, use statements, docblocks
- Creates backup before modification

#### Enforcement Flow
1. **PreToolUse Hook** → Blocks multi-class PHP files before Write/Edit operations
2. **Pre-Commit Hook** → Validates all staged PHP files, blocks commits with violations
3. **Result**: Violations blocked at both tool execution and commit level

### Validation Command
After writing PHP code, validate with these steps in order:
```bash
# Step 1: MANDATORY - PHPStan static analysis first
phpstan analyze <file> --level=max --no-progress

# Step 2: Apply Space-Utils paradigm standards
php $SPACE_UTILS_PATH/coding-standards/tools/auto-fixer.php <file>

# Step 3: Verify all issues resolved
phpstan analyze <file> --level=max --no-progress --error-format=table
```
**Note:** `php -l` is insufficient - always use PHPStan for real validation

### 🔧 PHPStan Error Handling Protocol

**CRITICAL: Diagnose First, Then Fix — NEVER Blindly Delete or Create**

When PHPStan reports errors, you MUST diagnose the root cause before acting.

#### Diagnostic Flow for "Not Found" Errors

For "Class not found", "Method not found", "Property not found":

1. **SEARCH FIRST** — Does the class/method/property exist in the codebase?
   - Search by class name, method name, or property name
   - Check for typos, case mismatches, or similar names
2. **If it EXISTS** — The reference is wrong. Fix it:
   - Wrong namespace import → Fix the `use` statement
   - Wrong class name (typo) → Fix the name
   - Wrong file location vs namespace → Fix PSR-4 compliance
3. **If it DOESN'T EXIST but SHOULD** — Create it:
   - Determine what it should do based on usage context
   - Create at the correct PSR-4 location
   - Run `composer dump-autoload`
4. **If it DOESN'T EXIST and SHOULDN'T** — Remove the reference:
   - The code references something that was never implemented
   - Remove the dead reference cleanly

#### Error Response Matrix

| Error Type | Step 1: Search | If Found | If Not Found |
|------------|---------------|----------|--------------|
| Class not found | Search codebase for the class | Fix namespace/import path | Create class OR remove if unneeded |
| Method not found | Check the class definition | Fix method name/signature | Add method OR remove call if unneeded |
| Property not found | Check the class definition | Fix property name | Add property OR remove access if unneeded |
| Type mismatch | Trace the data flow | Fix at the source where wrong type originates | Add proper type conversion |
| Interface not implemented | Check interface definition | Implement missing methods | — |

#### Fix Priority Order

1. **First**: Search and diagnose — understand WHY the error exists
2. **Second**: Fix references (wrong import, typo, namespace mismatch)
3. **Third**: Create missing dependencies if they should exist
4. **Fourth**: Remove dead references if the thing was never meant to exist
5. **NEVER**: Blindly delete code without understanding the cause

#### Pre-Change Baseline Workflow

Before modifying PHP files in a session:
```bash
# Step 1: Establish baseline error count
phpstan analyze app/ --level=max --no-progress 2>&1 | tail -5

# Step 2: Make your changes

# Step 3: Validate — error count must NOT increase
phpstan analyze app/ --level=max --no-progress 2>&1 | tail -5
```

#### Example: Class Not Found — Three Scenarios

Error: `Class "App\Logger\CrontabLogger" not found`

**Scenario A: Class exists but wrong import**
```php
// Wrong: use App\Logger\CrontabLogger;
// Right: use App\Logging\CrontabLogger;  // class is in Logging/, not Logger/
```

**Scenario B: Class doesn't exist but should**
```bash
# Create at PSR-4 location
# app/Logger/CrontabLogger.php
```

**Scenario C: Class was never meant to exist**
```php
// Remove the use statement and replace with the correct dependency
// e.g., use the framework's built-in logger instead
```

### 🔴 MANDATORY: Space-Utils Monad Preference

**ALWAYS prefer Space-Utils monads over native PHP functions!**

#### IString over native string functions
```php
use SpacePlatform\Utils\Functional\Monad\Scalar\IString;

// ❌ WRONG: Native PHP
$result = strtolower(str_replace('_', '-', $input));

// ✅ CORRECT: IString monad
$result = IString::of($input)->replace('_', '-')->lower()->get();
```

#### IArray over native array functions
```php
use SpacePlatform\Utils\Functional\Monad\Compound\IArray;

// ❌ WRONG: Native PHP
$filtered = array_filter($items, fn($i) => $i['active']);
$names = array_map(fn($i) => $i['name'], $filtered);

// ✅ CORRECT: IArray monad
$result = IArray::of($items)
    ->filter(fn($i) => $i['active'])
    ->map(fn($i) => $i['name']);
```

#### File/Directory over native filesystem functions
```php
use SpacePlatform\Utils\FileSystem\Component\File\File;
use SpacePlatform\Utils\FileSystem\Component\Directory\Directory;

// ❌ WRONG: Native PHP
$content = file_get_contents('/path/file.json');
$data = json_decode($content, true);

// ✅ CORRECT: File class (auto JSON handling)
$data = File::of('/path/file.json')->readFile();

// ❌ WRONG: Native PHP
$files = scandir('/path/to/dir');

// ✅ CORRECT: Directory class
foreach (Directory::of('/path/to/dir')->listFiles() as $file) {
    echo $file->getFileName();
}
```

### Quick Reference
- **ALWAYS**: Use `IString` for string operations (chainable, immutable)
- **ALWAYS**: Use `IArray` for array operations (functional, parallel-capable)
- **ALWAYS**: Use `File`/`Directory` for filesystem operations
- **ALWAYS**: Strong type everything (parameters, returns, properties)
- **NEVER**: Use native `str_*`, `array_*`, `file_*` when Space-Utils available
- **NEVER**: Create abstractions for single use cases
- **NEVER**: Over-engineer with unnecessary patterns

### 🔒 Environment Access Rules

❌ **FORBIDDEN in app/ directory:**
- `$_ENV[]` - Direct superglobal access
- `$_SERVER[]` - Direct superglobal access
- `env()` - Only allowed in config/ files
- `getenv()` - Only allowed in config/ files

✅ **REQUIRED:**
- Use `config()` helper to access configuration values

### 🧪 Test/Production Separation Rules

❌ **FORBIDDEN in app/ directory:**
- Mockery usage (`Mockery::`, `\Mockery\`)
- Environment checks (`env('APP_ENV') === 'testing'`)
- Test-only conditional logic (`if (isTestMode())`)
- Mock creation methods

✅ **REQUIRED:**
- Use dependency injection with interfaces
- Override bindings in test setUp() methods
- Keep all test doubles in test/ directory

## 🎯 QUESTION ANSWERING PRIORITY

**ANSWER THE ACTUAL QUESTION FIRST - FIXES COME SECOND!**

### Core Principle: Direct Response Priority
When the user asks a question, **ALWAYS answer that specific question first** before offering solutions or fixes. The user's intent matters more than solving problems they didn't ask you to solve.

### Response Priority Framework

#### When User Asks "Why?" or "What happened?"
**CORRECT Response Pattern:**
1. **FIRST**: Explain the root cause or reason
2. **SECOND**: Provide diagnostic information
3. **THIRD**: Only then offer to fix it (with permission)

**Example - Hook Failure:**
```
User: "Why didn't the claude hook work as expected?"

✅ CORRECT:
"The hook failed because it's looking for a configuration file at ~/.claude/hooks/config.json 
that doesn't exist. The error occurs at line 42 where it tries to parse undefined as JSON.

Would you like me to create the missing config file?"

❌ WRONG:
"Let me fix the hook configuration for you..."
[immediately starts editing files]
```

#### When User Asks "How?" or "What?"
**CORRECT Response Pattern:**
1. **FIRST**: Provide the explanation or information
2. **SECOND**: Give examples if helpful
3. **THIRD**: Offer to implement only if appropriate

**Example - Understanding Code:**
```
User: "What does this function do?"

✅ CORRECT:
"This function validates email addresses using a regex pattern. It checks for:
- Valid characters before the @
- A domain name after the @
- A valid TLD extension"

❌ WRONG:
"Let me refactor this function to be more efficient..."
```

#### When User Explicitly Requests Action
**CORRECT Response Pattern:**
- Take the requested action directly
- No need to over-explain unless asked

**Example - Direct Request:**
```
User: "Fix the type error in line 42"

✅ CORRECT:
[Directly fix the error]

❌ WRONG:
"Let me first explain what type errors are..."
```

### Question Type Detection Rules

**Diagnostic Questions** (Require explanation first):
- "Why didn't..."
- "What went wrong..."
- "How come..."
- "What caused..."
- "Can you explain..."

**Information Questions** (Require answers first):
- "What is..."
- "How does..."
- "What are the..."
- "Where is..."
- "When should..."

**Action Requests** (Direct implementation):
- "Fix..."
- "Implement..."
- "Create..."
- "Update..."
- "Change..."

### 🚨 CRITICAL: Resist the Urge to Fix

**When diagnosing issues:**
- ❌ **DON'T**: Jump to fixing before explaining
- ✅ **DO**: Explain the issue thoroughly first
- ✅ **DO**: Ask permission before fixing anything
- ✅ **DO**: Respect that understanding might be the only goal

**Remember:** Sometimes users just want to understand what happened, not have it fixed immediately. Your eagerness to solve problems should never override their actual request.

## 🧪 MANDATORY: Specification-First Testing

**ALL tests MUST be specifications of expected behavior, NOT confirmations of existing code.**

### Core Mandate
- **Tests are executable specifications** — they define what the code SHOULD do, not mirror what it DOES
- **TDD (red-green-refactor) is the ENFORCED DEFAULT** for all test writing
- **Never read implementation before writing a test** — write what the code should do based on requirements, interfaces, and consumer expectations
- **Test the consumer path, not internals** — if a server consumer boots your library, test that exact lifecycle path

### ❌ Confirmation Testing (FORBIDDEN Anti-Pattern)

Confirmation testing means: reading existing code, then writing tests that verify what the code already does. These tests pass by construction and miss real integration bugs.

```php
// ❌ CONFIRMATION TEST — Developer reads code, sees registerRoutes() exists:
test('registerRoutes adds routes') {
    $router = new Router();
    registerRoutes($router);            // calls internal function directly
    assert($router->has('/api/users'));  // confirms what code does
}
// This test PASSES but routes are NEVER registered in real server boot!

// ✅ SPECIFICATION TEST — Developer writes what a consumer expects:
test('server exposes API routes after boot') {
    $server = new Server(new LibraryIntegration());
    $server->boot();                    // real consumer lifecycle
    $response = $server->get('/api/users');
    assert($response->status === 200);  // tests real behavior
}
// This test FAILS if routes aren't registered during boot → catches the real bug
```

### Pre-Test Checklist (MANDATORY before writing ANY test)
- [ ] Am I testing from the **consumer's perspective**?
- [ ] Would this test **fail if the feature was broken** in real usage?
- [ ] Did I write this test **WITHOUT reading the implementation** first?
- [ ] Does the test name describe a **requirement**, not an implementation detail?

### Test Writing Sequence (ENFORCED)
1. **Understand the requirement** (from user story, interface, or feature description)
2. **Write the failing test** that specifies the expected behavior
3. **Run the test** — it MUST fail (red phase)
4. **Read the failing message** — it should clearly describe what's missing
5. **Only then** write the minimal implementation to make it pass (green phase)
6. **Refactor** while keeping tests passing

### Agent Routing Directive
When spawning ANY test agent (`testing-orchestrator`, `testing-unit-master`, `php-test-generator`, etc.), include this directive:
> "Write tests as specifications that define expected behavior from the consumer's perspective. Do NOT read implementation code before writing tests. Tests must fail if the feature is broken in real usage."

### 🔴 TRUE TDD WORKFLOW ENFORCEMENT

**This section enforces TRUE TDD (red-green-refactor) for new feature development.**

The key insight: Tests must FAIL initially. If a test passes immediately, it's confirmation testing (verifying what code does), not specification testing (defining what code should do).

#### The Red-Green-Refactor Cycle (MANDATORY)

```
┌─────────────────────────────────────────────────────────────┐
│  1. RED: Write failing test FIRST                           │
│     - Define expected behavior from consumer perspective    │
│     - Run test → MUST FAIL                                  │
│     - If it passes, you're doing confirmation testing       │
├─────────────────────────────────────────────────────────────┤
│  2. GREEN: Write MINIMAL code to pass                       │
│     - Only enough code to make test green                   │
│     - No extra features, no "nice to have"                  │
│     - Run test → MUST PASS                                  │
├─────────────────────────────────────────────────────────────┤
│  3. REFACTOR: Improve without changing behavior             │
│     - Clean up duplication                                  │
│     - Improve naming                                        │
│     - Run test → MUST STILL PASS                            │
└─────────────────────────────────────────────────────────────┘
```

#### TDD Workflow Tool: `bin/tdd-new-feature`

Use this script to start new features with enforced TDD:

```bash
# Create a new feature with TDD workflow enforcement
./bin/tdd-new-feature "FeatureName"

# Or with custom test path
./bin/tdd-new-feature "FeatureName" "tests/Specification/CustomSpecTest.php"
```

The script:
1. Creates a spec test file with deliberate failure (RED phase)
2. Runs the test to confirm it fails
3. Provides guidance for the green-refactor phases

#### Spec Test Template: `tests/Specification/.template.php`

Copy this template when creating spec tests manually:
- Contains consumer-perspective test structure
- Includes red phase checklist
- Has Given/When/Then comment structure
- Shows correct vs incorrect test naming

#### Pre-Implementation Checklist (MANDATORY)

Before writing ANY implementation code for a new feature:
- [ ] Spec test file exists in `tests/Specification/`
- [ ] Test has been run and **FAILED** (red phase confirmed)
- [ ] Failure message clearly describes what's missing
- [ ] No implementation code written yet

#### TDD Verification Checklist (Before Declaring "Done")

- [ ] **Spec test written BEFORE implementation**
- [ ] **Test ran and FAILED (red phase)**
- [ ] **Minimal code written to pass**
- [ ] **Test passes (green phase)**
- [ ] **Code refactored while tests pass**
- [ ] **PHPStan clean**: `./vendor/bin/phpstan analyze`
- [ ] **All tests pass**: `./vendor/bin/phpunit`

#### Common TDD Anti-Patterns to Avoid

| Anti-Pattern | Why It's Wrong | Correct Approach |
|--------------|----------------|------------------|
| Reading implementation before test | Creates confirmation tests | Write test from requirements only |
| Test passes immediately | Test doesn't verify anything new | Ensure test fails first (red phase) |
| Writing more than needed to pass | Gold-plating, feature creep | Minimal implementation only |
| Skipping refactor phase | Technical debt accumulation | Always clean up after green |
| Testing internal methods | Implementation lock-in | Test consumer-visible behavior |

## 🚨 MANDATORY COMPLEXITY TRIAGE SYSTEM

**Categorize complexity before implementing any solution:**

### Complexity Classification (MANDATORY FIRST STEP)
Before implementing ANY solution, you MUST categorize the problem:

#### 🟢 SIMPLE (Default Response)
- **Single file changes** or basic functionality
- **Existing patterns** can be reused
- **Direct implementation** without architectural changes
- **Time estimate**: < 30 minutes

**Required Response**: Minimal solution using existing patterns

#### 🟡 MEDIUM (Requires Justification)
- **Multiple file coordination** needed
- **New patterns** required but within existing architecture
- **Limited scope** impact (1-2 modules)
- **Time estimate**: 30 minutes - 2 hours

**Required Response**: Justify complexity, propose simple alternative first

#### 🔴 COMPLEX (Requires User Approval)
- **Architectural changes** or new abstractions
- **System-wide impact** (3+ modules affected)
- **New dependencies** or paradigm shifts
- **Time estimate**: > 2 hours

**Required Response**: Get explicit user approval: "This requires complex changes affecting [X]. Proceed? Simple alternative: [Y]"

### 🚨 HARD OVER-ENGINEERING BLOCKERS

**Blocked patterns (user override required):**

#### Architectural Over-Engineering Blockers
❌ **FORBIDDEN**: Creating abstractions for single use cases
❌ **FORBIDDEN**: Adding layers without concrete need (3+ real use cases)
❌ **FORBIDDEN**: Implementing patterns "for future flexibility"
❌ **FORBIDDEN**: Creating frameworks within applications
❌ **FORBIDDEN**: Over-modularizing simple functionality

#### Code Pattern Blockers
❌ **FORBIDDEN**: Factory patterns for <3 variants
❌ **FORBIDDEN**: Observer patterns for simple callbacks
❌ **FORBIDDEN**: Strategy patterns for <4 strategies
❌ **FORBIDDEN**: Repository patterns for single data source
❌ **FORBIDDEN**: Dependency injection for <3 dependencies

#### File Structure Blockers
❌ **FORBIDDEN**: Folders for <3 related files
❌ **FORBIDDEN**: Separate config files for simple settings
❌ **FORBIDDEN**: Interface files with single implementation
❌ **FORBIDDEN**: Utility classes for single functions
❌ **FORBIDDEN**: Service layers for simple CRUD operations

### 🔍 MANDATORY COMPLEXITY REALITY CHECKS

**STOP AND ANSWER THESE BEFORE CONTINUING**

#### Reality Check (Before, During & After Implementation)
- [ ] Can this be solved with <10 lines of code changes?
- [ ] Does an existing pattern solve 80% of this?
- [ ] Am I solving problems that don't exist yet? (YAGNI)
- [ ] Will a junior developer understand this immediately?
- [ ] Could I delete 30% and still have it work?

### 🔒 PROGRESSIVE COMPLEXITY ENFORCEMENT

**ESCALATING MEASURES FOR COMPLEXITY CONTROL**

#### Level 1: Automatic Simplification (SIMPLE problems)
- **Auto-apply**: Existing patterns and minimal changes
- **No user interaction**: Proceed with simplest solution
- **Documentation**: Single-line explanation of approach

#### Level 2: Justified Complexity (MEDIUM problems)
- **Required justification**: "This complexity is needed because..."
- **Alternative proposal**: "Simpler alternative would be..."
- **Impact assessment**: "This affects [specific modules/files]"
- **User opt-out**: "Reply 'simple' for minimal approach instead"

#### Level 3: Explicit Approval (COMPLEX problems)
- **Mandatory pause**: Stop and request approval before proceeding
- **Detailed breakdown**: Full implementation plan with complexity costs
- **Simple alternative**: Always provide a simpler option
- **User consent**: Require explicit "proceed with complex solution"

#### Level 4: Complexity Budget (Project-wide)
- **Track complexity debt**: Maintain running total of complex solutions
- **Budget limits**: Maximum 3 complex solutions per project
- **Require cleanup**: Must simplify existing code before adding complexity
- **Audit requirement**: Review all complex solutions monthly

### 🎛️ USER OVERRIDE MECHANISM

**EXPLICIT CONTROLS FOR INTENTIONAL COMPLEXITY**

#### Override Commands
```yaml
# User can explicitly request complex solutions
user_override:
  complexity_mode: "simple"        # simple | justified | complex | unrestricted
  pattern_enforcement: "strict"    # strict | relaxed | disabled
  reality_checks: "enabled"       # enabled | warnings | disabled
  auto_simplify: "aggressive"     # aggressive | moderate | minimal
```

#### Override Scenarios
**"I need the complex solution"**: Bypass blockers for specific request
**"Disable simplicity checks"**: Temporarily allow over-engineering
**"Performance critical"**: Override for optimization requirements
**"Future-proofing required"**: Allow abstractions for known future needs

#### Override Documentation
- **Justification required**: Why complexity is necessary
- **Complexity budget**: Tracks against project complexity limits
- **Review reminder**: Scheduled cleanup/simplification review
- **Rollback plan**: How to simplify if complexity proves unnecessary

### 🚨 COMPLEXITY VIOLATION RESPONSES

**AUTOMATED RESPONSES TO OVER-ENGINEERING**

#### Detection Triggers
- **Abstraction without multiple use cases**: Auto-suggest inline implementation
- **Deep inheritance**: Propose composition alternative
- **Excessive configuration**: Suggest convention over configuration
- **Premature optimization**: Request performance benchmarks first
- **Gold-plating**: Strip to MVP and ask for explicit feature requests

#### Violation Response Workflow
1. **Immediate stop**: Halt implementation at violation point
2. **Simplification proposal**: Offer concrete simpler alternative
3. **User choice**: Simple solution vs. justified complexity
4. **Implementation**: Proceed with chosen approach
5. **Documentation**: Record complexity decision and rationale

## 🚨 MANDATORY FILE CREATION CONSTRAINTS

**Minimize file creation - consolidate instead:**

### Core File Creation Principles
- **NEVER create new files unless absolutely critical for core functionality**
- **ALWAYS consolidate related content into existing files**
- **MAXIMUM 5 new files per feature implementation**
- **MANDATORY justification required for any new file creation**

### Enforced File Creation Hierarchy
1. **FIRST**: Edit existing files to add functionality
2. **SECOND**: Consolidate multiple related concepts into single files
3. **THIRD**: Use progressive disclosure within existing files
4. **LAST RESORT**: Create new files only if impossible to avoid

### File Creation Quality Gates
Before creating ANY new file, you MUST:
- [ ] Verify no existing file can be extended
- [ ] Confirm consolidation is impossible
- [ ] Document why existing patterns don't work
- [ ] Get explicit user approval for new files

## 📚 DOCUMENTATION MINIMALISM MANDATE

### Single Source of Truth Principle
**ELIMINATE DOCUMENTATION DUPLICATION AND PROLIFERATION**

- **ONE comprehensive file per major topic maximum**
- **Embed examples within primary documentation**
- **Use progressive disclosure sections, not separate files**
- **Maximum documentation hierarchy: 3 levels deep**

### Forbidden Documentation Patterns
❌ **NEVER create**: Multiple README files
❌ **NEVER create**: Separate example files
❌ **NEVER create**: Topic-specific sub-documentation
❌ **NEVER create**: Tutorial series as separate files

## 🚨 AUTOMATED CHECKS ARE MANDATORY

**ALL hook issues are BLOCKING - EVERYTHING must be ✅ GREEN!**
No errors. No formatting issues. No linting problems. Zero tolerance.
These are not suggestions. Fix ALL issues before continuing.

## CRITICAL WORKFLOW - ALWAYS FOLLOW THIS!

### Research → Plan → Implement

**NEVER JUMP STRAIGHT TO CODING!** Always follow this sequence:

1. **Research**: Explore the codebase, understand existing patterns
2. **Plan**: Create a detailed implementation plan and verify it with me
3. **Implement**: Execute the plan with validation checkpoints

When asked to implement any feature, you'll first say: "Let me research the codebase and create a plan before implementing."

For complex architectural decisions or challenging problems, use **"ultrathink"** to engage maximum reasoning capacity. Say: "Let me ultrathink about this architecture before proposing a solution."

### USE MULTIPLE AGENTS!

*Leverage subagents aggressively* for better results:

- Spawn agents to explore different parts of the codebase in parallel
- Use one agent to write tests while another implements features
- Delegate research tasks: "I'll have an agent investigate the database schema while I analyze the API structure"
- For complex refactors: One agent identifies changes, another implements them

Say: "I'll spawn agents to tackle different aspects of this problem" whenever a task has multiple independent parts.

## 🚀 MANDATORY MULTI-AGENT EXECUTION

**AUTOMATIC AGENT SPAWNING IS NOW REQUIRED - NOT OPTIONAL!**

### 🔴 CRITICAL: Default to Parallel Execution

**You MUST spawn multiple agents automatically when ANY of these triggers are detected:**

#### Complexity-Based Auto-Triggers (MANDATORY)
```yaml
automatic_agent_spawning:
  file_operations:
    trigger: "Files to modify >= 5"
    action: "MUST spawn file-processor agents in parallel"
    justification: "3-5x performance improvement guaranteed"
    
  test_scenarios:
    trigger: "Test failures >= 3 OR test files >= 10"
    action: "MUST spawn testing-orchestrator with adaptive agents"
    justification: "Parallel test analysis and fixing"
    
  code_quality:
    trigger: "Linting errors >= 10 OR complexity warnings"
    action: "MUST spawn quality-enforcer agents"
    justification: "Comprehensive parallel quality improvements"
    
  documentation:
    trigger: "Multiple modules need docs OR API endpoints >= 5"
    action: "MUST spawn doc-module-generator per module"
    justification: "Parallel documentation generation"
    
  debugging:
    trigger: "Error spans multiple files OR stack trace > 10 lines"
    action: "MUST spawn debugging-orchestrator agents"
    justification: "Multi-angle investigation required"
```

### 🎯 Pattern Recognition Triggers

**AUTOMATICALLY spawn agents when detecting these patterns:**

#### Code Analysis Patterns
- **Multiple similar changes**: Spawn agents for each change location
- **Cross-module dependencies**: Spawn agents per module for parallel analysis
- **Refactoring opportunities**: Spawn pattern-classifier and refactoring agents
- **Performance bottlenecks**: Spawn perf-sql-optimizer and analysis agents

#### Development Workflow Patterns
```yaml
research_triggers:
  - "Understanding codebase structure" → research-orchestrator (4+ agents)
  - "Investigating multiple files" → infra-context-discovery agents
  - "Exploring dependencies" → dependency-mapper agents
  - "Finding patterns" → pattern-classifier agents

implementation_triggers:
  - "Creating multiple components" → Parallel component generators
  - "Adding CRUD operations" → Separate agents per operation
  - "Implementing API endpoints" → Agent per endpoint group
  - "Writing test suites" → testing-orchestrator with type-specific agents
```

### 🚨 MANDATORY AGENT-FIRST MINDSET

**Replace single-threaded thinking with multi-agent patterns:**

#### ❌ OLD (FORBIDDEN) Approach:
```markdown
"Let me search through files one by one..."
"I'll fix each test failure sequentially..."
"Let me analyze this error step by step..."
```

#### ✅ NEW (REQUIRED) Approach:
```markdown
"I'll spawn multiple agents to search different areas in parallel..."
"Deploying test-fixer agents to handle all failures simultaneously..."
"Launching debugging agents to investigate from multiple angles..."
```

### 📊 Automatic Complexity Scoring

**Calculate complexity score and REQUIRE agents based on thresholds:**

```python
def calculate_task_complexity(context):
    score = 0
    score += context.files_to_modify * 10
    score += context.test_failures * 15
    score += context.lines_of_code / 100
    score += context.dependencies * 5
    score += context.error_complexity * 20
    
    if score >= 100:  # HIGH COMPLEXITY
        return "MANDATORY: Spawn 5+ specialized agents"
    elif score >= 50:  # MEDIUM COMPLEXITY
        return "REQUIRED: Spawn 3-4 agents minimum"
    elif score >= 20:  # LOW-MEDIUM COMPLEXITY
        return "ADVISED: Spawn 2-3 agents for efficiency"
    else:
        return "OPTIONAL: Single execution acceptable"
```

### 🎮 Framework-Specific Agent Deployment

**AUTOMATICALLY detect framework and spawn appropriate agents:**

```yaml
framework_detection:
  react_project:
    triggers: ["package.json has 'react'", "*.jsx files exist"]
    agents: ["component-generator", "hook-creator", "test-writer"]
    
  php_project:
    triggers: ["composer.json exists", "*.php files"]
    agents: ["php-transformer", "space-utils-enforcer", "test-fixer"]
    
  api_project:
    triggers: ["OpenAPI spec", "routes/ directory", "controllers/"]
    agents: ["api-documenter", "endpoint-tester", "security-scanner"]
    
  python_project:
    triggers: ["requirements.txt", "*.py files", "setup.py"]
    agents: ["type-checker", "test-runner", "dependency-manager"]
```

### 🚀 Proactive Agent Spawning Rules

**SPAWN AGENTS BEFORE BEING ASKED when you detect:**

1. **Multi-File Operations**
   - Immediately spawn parallel file processors
   - Say: "I'm spawning agents to handle these files in parallel"

2. **Error Investigation**
   - Auto-spawn debugging-orchestrator
   - Say: "Deploying specialized debugging agents to investigate"

3. **Test Operations**
   - Auto-spawn testing-orchestrator
   - Say: "Launching test agents for comprehensive coverage"

4. **Documentation Tasks**
   - Auto-spawn doc generators
   - Say: "Deploying documentation agents for parallel generation"

5. **Refactoring Operations**
   - Auto-spawn refactoring agents
   - Say: "Spawning refactoring agents to handle each module"

### 📈 Agent Usage Metrics

**Track and optimize agent usage:**

```yaml
agent_performance_tracking:
  minimum_targets:
    simple_tasks: 0-1 agents (only if truly trivial)
    medium_tasks: 2-4 agents (should be default)
    complex_tasks: 5+ agents (mandatory for any complexity)
    
  success_metrics:
    parallelization_rate: ">75% of tasks use multiple agents"
    performance_gain: "3-5x speedup average"
    coverage_improvement: "100% of complex patterns trigger agents"
```

### 🔒 Quality Gates for Agent Usage

**Your response MUST include agents if:**
- [ ] Task involves 3+ files → Parallel file agents REQUIRED
- [ ] Multiple similar operations → Agent per operation REQUIRED
- [ ] Cross-cutting concerns → Specialized agents REQUIRED
- [ ] Research needed → research-orchestrator REQUIRED
- [ ] Testing involved → testing-orchestrator REQUIRED

### 💡 Agent Spawning Examples

```markdown
User: "Fix the failing tests"
→ "Spawning test-fixer agents to handle failures in parallel"

User: "Update the API documentation"
→ "Deploying doc-api-documenter agents for each endpoint group"
```

### 🚨 CRITICAL: Agent Usage Validation

**Before completing ANY task, verify:**
- ✅ Did I spawn agents for parallelizable work?
- ✅ Did I use specialized agents for complex patterns?
- ✅ Did I leverage the full agent ecosystem (82 agents available)?
- ✅ Did I default to multi-agent instead of sequential execution?

**If the answer to ANY of these is "No", you MUST reconsider your approach!**

### 🎛️ USER-CONTROLLED VERBOSITY SYSTEM

**Let users choose their complexity level:**

```yaml
# User verbosity preferences (configurable)
claude_config:
  verbosity_level: "minimal"  # minimal | standard | comprehensive
  file_generation: "conservative"  # conservative | balanced | permissive
  documentation_style: "consolidated"  # consolidated | detailed | extensive
```

#### Verbosity Level Behaviors

**Minimal Mode (Default)**:
- Single files for major topics
- Essential functionality only
- No separate example files (embed examples)
- Maximum 10 total new files per project

**Standard Mode**:
- Moderate file organization
- Core + some advanced functionality
- Limited separate files when justified
- Maximum 25 total new files per project

**Comprehensive Mode**:
- Full file organization when explicitly requested
- All functionality exposed
- Separate files allowed with user approval
- Maximum 50 total new files per project

### Reality Checkpoints

**Stop and validate** at these moments:

- After implementing a complete feature
- Before starting a new major component
- When something feels wrong
- Before declaring "done"
- **WHEN HOOKS FAIL WITH ERRORS** ❌

Run: `make fmt && make test && make lint`

> Why: You can lose track of what's actually working. These checkpoints prevent cascading failures.

## 🔧 TEMPLATE CONSOLIDATION MANDATE

### Template Inheritance System
**ELIMINATE TEMPLATE DUPLICATION THROUGH SMART INHERITANCE**

- **Base templates only**: Maximum 3 base templates per language
- **Composition over creation**: Combine templates, don't duplicate
- **Parameterized templates**: Use variables instead of separate files
- **Conditional sections**: Use templating logic, not file variants

### Template File Limits
- **Languages**: 1 base template + 1 enhanced template maximum
- **Frameworks**: Inherit from language base, add minimal extensions
- **Workflows**: Single configurable template with parameters
- **Commands**: Consolidate related commands into single template

## 🔍 PROGRESSIVE DISCLOSURE BY DEFAULT

### On-Demand Content Generation
**GENERATE CONTENT WHEN NEEDED, NOT UPFRONT**

- **Default behavior**: Show minimal interface, expand on request
- **Contextual revelation**: Present details based on user actions
- **Just-in-time documentation**: Generate specific help when requested
- **Usage-driven expansion**: Create content based on actual need

### Progressive Interface Rules
- **Initial view**: Core functionality only (80/20 rule)
- **Expansion triggers**: User-initiated or context-driven
- **Content layering**: Basic → Intermediate → Advanced on demand
- **Memory optimization**: Cache frequently accessed, purge unused

## 🛡️ MANDATORY FILE PROLIFERATION QUALITY GATES

### Pre-Creation Validation Pipeline
**EVERY FILE CREATION MUST PASS ALL GATES**

#### Gate 1: Necessity Validation
- [ ] Functionality impossible without new file
- [ ] No existing file can accommodate content
- [ ] Consolidation approaches exhausted
- [ ] User explicitly requests separate file

#### Gate 2: Maintenance Impact Assessment
- [ ] File adds <10% to total project complexity
- [ ] Clear ownership and update responsibility
- [ ] Integration with existing files validated
- [ ] Removal/consolidation path documented

#### Gate 3: User Experience Impact
- [ ] Navigation complexity not increased
- [ ] Cognitive load impact measured as acceptable
- [ ] File discovery mechanisms sufficient
- [ ] User workflow improvement demonstrated

### Automatic Rejection Criteria
❌ **Auto-reject if**:
- File count exceeds verbosity level limits
- Similar content exists elsewhere
- Can be achieved through editing existing files
- No clear maintenance plan exists
- Increases navigation depth unnecessarily

## 🔄 FUNCTIONALITY PRESERVATION REQUIREMENTS

### Backward Compatibility Guarantees
**ZERO FUNCTIONALITY LOSS DURING FILE CONSOLIDATION**

- **All existing commands**: Must work with consolidated structure
- **Template functionality**: Preserved through inheritance/composition
- **User workflows**: Maintained through intelligent redirects
- **Integration points**: Updated automatically during consolidation

## 📂 INTELLIGENT FOLDER ORGANIZATION

### Mild Organization Principles
**BALANCED CATEGORIZATION - NOT TOO AGGRESSIVE**

- **Maximum 3 folder levels** (respecting cognitive load limits)
- **Minimum 3 similar files** before creating folders
- **Framework-aware organization** respecting ecosystem conventions
- **User override capability** for all organization decisions

### Semantic Categorization Rules

#### Automatic Folder Creation Triggers
- **Tests**: Files ending in .test, .spec, or in __tests__ → tests/
- **Configuration**: Config files, .env, settings → config/
- **Utilities**: Helper functions, shared code → utils/
- **Documentation**: .md files (except README) → docs/

#### Content-Based Grouping (Mild)
- **Similar imports**: Files importing same dependencies → consider grouping
- **Naming patterns**: Shared prefixes (use*, *Config, *Helper) → semantic folders
- **Framework detection**: React components → components/, API routes → routes/

#### Folder Creation Limits
- **Maximum 7 items** per folder before suggesting subdivision
- **Respect file creation limits**: Folders count toward 5-file maximum
- **User approval required** for new folder creation
- **Rollback capability** for all organization changes

### Organization Quality Gates
Before creating ANY folder, you MUST:
- [ ] Verify 3+ files would benefit from grouping
- [ ] Confirm folder depth stays ≤3 levels
- [ ] Check framework conventions allow organization
- [ ] Get explicit user approval for folder creation

### Forbidden Organization Patterns
❌ **NEVER create**: Folders deeper than 3 levels
❌ **NEVER organize**: Single files (exception: framework requirements)
❌ **NEVER separate**: Tightly coupled files
❌ **NEVER override**: Framework-mandated structures

### Similarity Detection Rules
**Reasonable Similarity Thresholds for Grouping:**

#### High Similarity (75-90%) → Same Folder
- Files with shared function signatures
- Similar import patterns and dependencies
- Matching naming conventions (prefixes/suffixes)
- Related business domain terminology

#### Medium Similarity (50-75%) → Suggest Grouping
- Related functionality but different implementation
- Shared framework patterns (components, hooks, services)
- Similar file types with related purposes
- Common configuration or utility patterns

#### Low Similarity (<50%) → Keep Separate
- Different domains or purposes
- Unrelated functionality
- Different architectural layers
- Independent utility functions

### Framework-Aware Organization
**Respect Established Ecosystem Patterns:**

- **React/Next.js**: components/, pages/, hooks/, utils/
- **Vue.js**: components/, views/, composables/, utils/
- **Angular**: components/, services/, modules/, pipes/
- **Express/Node.js**: routes/, middleware/, controllers/, models/
- **Django**: models/, views/, templates/, static/
- **FastAPI/Flask**: routes/, models/, schemas/, utils/

## 🚫 ENHANCED PACKAGE POLICY

**STRICT PROHIBITION ON ENHANCED PACKAGE USAGE**

Don't use any enhanced package. Either merge the original file with the enhanced file, or replace the original files with the enhanced files.

### Backup File Types and Retention
- **`.autofix-backup.*`**: Temporary files created during PHP paradigm auto-fixes
  - **Retention**: Immediate cleanup after operation completion
  - **Purpose**: Safety during automatic code transformations
  - **Cleanup**: Automatic via post-edit hooks and commit triggers

- **`.backup`**: Manual or persistent backup files
  - **Retention**: 24 hours (configurable via `CLAUDE_MERGE_BACKUP_RETENTION`)
  - **Purpose**: User-created backups or rollback points
  - **Cleanup**: Periodic via post-commit hook cleanup process

- **`.pre-rollback.*`**: Pre-rollback safety backups
  - **Retention**: 48 hours (longer safety period)
  - **Purpose**: Safety before major operations
  - **Cleanup**: Post-commit hook with extended retention

### Hook Operation Examples
```bash
# Successful hook execution removes autofix backups immediately:
[2025-08-19 22:16:23] Removed autofix backup files:
  - ./src/MySQLDao.php.autofix-backup.1755581443.68a40c03dbfb4
  - [... 75 additional files cleaned ...]

# Manual backups preserved according to retention policy:
src/MySQLDao.php.backup (retained - within 24h policy)
```

### Understanding Hook Messages
When hooks report "removed X autofix backup files" - this is **correct operation**:
- ✅ Autofix backups are temporary and should be cleaned immediately
- ✅ Different backup types follow different retention schedules
- ✅ Manual `.backup` files are intentionally preserved longer

### 🚨 CRITICAL: Hook Failures Are BLOCKING

## 🎯 MANDATORY SPECIALIZED AGENT ROUTING

**STOP DEFAULTING TO GENERIC AGENTS! USE SPECIALIZED AGENTS!**

### 🚫 "coder" is a FORBIDDEN DEFAULT

**The generic `coder` agent is ONLY for tasks matching NO specialized agent.**

Before spawning ANY agent, you MUST:
1. Check the routing table below
2. Find the most specific agent for the task
3. NEVER use `coder` if a specialized agent exists

### 📋 MANDATORY Agent Routing Table

| Task Pattern | REQUIRED Agent | ❌ NOT coder |
|-------------|----------------|--------------|
| **PHP files (*.php)** | `php-transformer` | ❌ |
| **PHP tests** | `php-test-generator` | ❌ |
| **PHP coverage** | `php-test-coverage-analyzer` | ❌ |
| **SQL/query optimization** | `perf-sql-optimizer` | ❌ |
| **Test failures** | `testing-orchestrator` or `cicd-test-fixer` | ❌ |
| **Unit tests** | `testing-unit-master` | ❌ |
| **Integration tests** | `testing-integration-master` | ❌ |
| **API testing** | `testing-api-integration` | ❌ |
| **Git operations** | `infra-git-operator` | ❌ |
| **API documentation** | `doc-api-documenter` | ❌ |
| **README generation** | `doc-readme-generator` | ❌ |
| **Getting started guides** | `doc-getting-started` | ❌ |
| **Architecture decisions** | `doc-architecture-designer` | ❌ |
| **Changelog** | `doc-changelog-writer` | ❌ |
| **Research tasks** | `research-orchestrator` | ❌ |
| **Code quality issues** | `quality-code-analyzer` or `quality-enforcer` | ❌ |
| **Security scanning** | `quality-security-scan` | ❌ |
| **Build failures** | `cicd-build-fixer` | ❌ |
| **Deployment issues** | `cicd-deployment-fixer` | ❌ |
| **Dependency updates** | `infra-dependency-manager` | ❌ |
| **TypeScript code** | `typescript-coder` | ❌ |
| **JavaScript code** | `js-coder` | ❌ |
| **Python code** | `python-coder` | ❌ |
| **Go code** | `go-coder` | ❌ |
| **Rust code** | `rust-coder` | ❌ |
| **Codebase exploration** | `Explore` | ❌ |
| **Implementation planning** | `Plan` | ❌ |
| **Multi-agent coordination** | `infra-orchestrator` | ❌ |

### 🔍 Pre-Spawn Agent Selection Check

**BEFORE spawning ANY agent, ask yourself:**

```
1. What file type am I working with?
   → PHP? Use php-transformer
   → TypeScript? Use typescript-coder
   → Python? Use python-coder

2. What operation am I performing?
   → Testing? Use testing-orchestrator
   → Documentation? Use doc-* agents
   → Git? Use infra-git-operator

3. Is there a SPECIALIZED agent for this exact task?
   → Yes? USE IT!
   → No? Only then consider generic agents

4. Am I about to use "coder"?
   → STOP! Find the specialized agent instead!
```

### 🚨 Agent Selection Enforcement

**If you're about to use `coder` or `generic-coder`:**

1. **STOP immediately**
2. **Re-read the routing table above**
3. **Find the specialized agent**
4. **Justify if truly no match exists**

### 💬 Required Agent Announcement Format

When spawning agents, ALWAYS announce which specialized agent and WHY:

```markdown
✅ CORRECT:
"Spawning `php-transformer` agent for PHP file modifications"
"Deploying `testing-orchestrator` to handle test failures"
"Using `perf-sql-optimizer` for query performance analysis"

❌ WRONG:
"Spawning coder agent to fix this"
"Using generic agent for this task"
```

### 🎯 Language-Specific Agent Priority

**ALWAYS use language-specific coders over generic:**

| Language | Specialized Agent | Use For |
|----------|------------------|---------|
| PHP | `php-transformer` | All PHP code, Space-Utils compliance |
| TypeScript | `typescript-coder` | Type-safe TS development |
| JavaScript | `js-coder` | Modern JS/Node.js patterns |
| Python | `python-coder` | Pythonic code, type hints |
| Go | `go-coder` | Idiomatic Go, performance |
| Rust | `rust-coder` | Memory-safe Rust code |

### 🔄 Fallback Hierarchy

**Only fall back to generic agents in this order:**

```
1. Exact specialized agent (e.g., php-transformer)
   ↓ not available
2. Category specialist (e.g., testing-orchestrator)
   ↓ not available
3. Domain expert (e.g., quality-code-analyzer)
   ↓ not available
4. Language-specific coder (e.g., typescript-coder)
   ↓ not available
5. generic-coder (LAST RESORT with justification)
```

### ✅ Agent Usage Validation Checklist

**Before completing ANY multi-agent task, verify:**

- [ ] Did I use specialized agents for each subtask?
- [ ] Did I avoid generic `coder` when specialists exist?
- [ ] Did I announce which agent and why?
- [ ] Did I follow the routing table?
- [ ] Can I justify any generic agent usage?

**If ANY answer is "No", reconsider your agent selection!**
