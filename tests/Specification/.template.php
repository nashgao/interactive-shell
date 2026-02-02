<?php

/**
 * SPECIFICATION TEST TEMPLATE
 *
 * Copy this file to create a new specification test.
 * Rename to [FeatureName]SpecTest.php
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * IMPORTANT: TRUE TDD WORKFLOW
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * This template enforces the RED-GREEN-REFACTOR cycle:
 *
 * 1. RED PHASE (You are here!)
 *    - Write this test FIRST (before any implementation)
 *    - Run test → It MUST FAIL
 *    - Failure message should describe what's missing
 *
 * 2. GREEN PHASE
 *    - Write MINIMAL code to make test pass
 *    - No extra features, no "nice to have"
 *    - Run test → It MUST PASS
 *
 * 3. REFACTOR PHASE
 *    - Clean up duplication
 *    - Improve naming
 *    - Run test → It MUST STILL PASS
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * SPECIFICATION-FIRST MINDSET
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Tests define WHAT the code should do (specification), NOT confirm WHAT it does.
 *
 * ❌ WRONG (Confirmation Testing):
 *    - Read implementation code
 *    - Write test that mirrors what code does
 *    - Test passes immediately → false confidence
 *
 * ✅ RIGHT (Specification Testing):
 *    - Define consumer expectation from requirements
 *    - Write test BEFORE implementation
 *    - Test fails initially → proves it tests something real
 *    - Implementation makes test pass → proves feature works
 */

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Specification;

use PHPUnit\Framework\TestCase;

/**
 * [Feature Name] Consumer Specification Tests.
 *
 * These tests define expected behavior from the CONSUMER's perspective.
 * A consumer is someone who uses this library to:
 * - [Describe consumer use case 1]
 * - [Describe consumer use case 2]
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * PRE-TEST CHECKLIST (MANDATORY before writing ANY test):
 * ═══════════════════════════════════════════════════════════════════════════
 * - [ ] Am I testing from the CONSUMER'S perspective?
 * - [ ] Would this test FAIL if the feature was broken in real usage?
 * - [ ] Did I write this test WITHOUT reading implementation first?
 * - [ ] Does the test name describe a REQUIREMENT, not an implementation?
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * RED PHASE VERIFICATION (verify BEFORE writing implementation):
 * ═══════════════════════════════════════════════════════════════════════════
 * - [ ] Test was written FIRST (no implementation code exists yet)
 * - [ ] Test ran and FAILED
 * - [ ] Failure message clearly describes what's missing
 * - [ ] No implementation code has been read or written
 */
final class FeatureNameSpecTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════════
    // SETUP: Create the infrastructure a consumer would use
    // ═══════════════════════════════════════════════════════════════════════

    protected function setUp(): void
    {
        parent::setUp();

        // TODO: Set up test infrastructure from consumer's perspective
        // Example:
        // $this->server = new TestServer();
        // $this->transport = new InMemoryTransport($this->server);
        // $this->shell = new Shell($this->transport);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SPECIFICATION TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * SPECIFICATION: Consumer can [primary feature action].
     *
     * This is the MAIN specification for this feature.
     * It tests the primary consumer use case.
     */
    public function testConsumerCanPerformPrimaryAction(): void
    {
        // Given: Consumer has [preconditions]
        // TODO: Set up the preconditions

        // When: Consumer [performs action]
        // TODO: Perform the consumer action

        // Then: Consumer [sees expected outcome]
        $this->fail(
            'RED PHASE: Replace this with actual specification test. ' .
            'Test what consumer EXPECTS, not what code DOES.'
        );
    }

    /**
     * SPECIFICATION: Consumer receives clear error when [error condition].
     *
     * Error handling specification - consumers need clear feedback.
     */
    public function testConsumerReceivesClearErrorOnFailure(): void
    {
        // Given: Consumer has [preconditions for failure]

        // When: Consumer [performs action that should fail]

        // Then: Consumer receives [specific error information]
        $this->fail(
            'RED PHASE: Replace this with error handling specification.'
        );
    }

    /**
     * SPECIFICATION: Consumer can [secondary feature action].
     *
     * Additional consumer capability.
     */
    public function testConsumerCanPerformSecondaryAction(): void
    {
        // Given: [preconditions]

        // When: [action]

        // Then: [outcome]
        $this->fail(
            'RED PHASE: Replace this with secondary action specification.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EDGE CASES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * SPECIFICATION: Consumer can [action] even when [edge condition].
     */
    public function testConsumerHandlesEdgeCase(): void
    {
        // Given: [edge case preconditions]

        // When: [action under edge conditions]

        // Then: [expected graceful handling]
        $this->fail(
            'RED PHASE: Replace this with edge case specification.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEMPLATE HELPERS - Remove before finalizing test
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Test naming convention examples:
     *
     * ✅ GOOD (describes consumer requirement):
     *   - testConsumerCanExecuteCommandAndReceiveOutput
     *   - testConsumerReceivesErrorOnInvalidInput
     *   - testConsumerCanFilterMessagesByPattern
     *   - testConsumerCanReconnectAfterDisconnection
     *
     * ❌ BAD (describes implementation detail):
     *   - testParseMethodReturnsArray
     *   - testHandlerCallsInternalMethod
     *   - testConstructorSetsProperties
     *   - testPrivateMethodWorks
     */
}
