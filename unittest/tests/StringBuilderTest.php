<?php

declare(strict_types=1);

use orange\model\StringBuilder;

final class StringBuilderTest extends unitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = new StringBuilder();
    }

    /* Public Method Tests */

    public function testAppendJoinsWithTheSeparator(): void
    {
        $this->assertInstanceOf(StringBuilder::class, $this->instance->append('SELECT', '*'));
        $this->assertEquals('SELECT *', $this->instance->get());
    }

    public function testAppendTakesAnyNumberOfArguments(): void
    {
        $this->instance->append('a', 'b', 'c');

        $this->assertEquals('a b c', $this->instance->get());
    }

    /**
     * The reason this class has a test at all: the guard used to be empty(),
     * which throws away '0' along with ''. A zero is a perfectly good SQL
     * fragment - it is how 'LIMIT 10 OFFSET 0' lost its offset.
     */
    public function testAppendKeepsAZero(): void
    {
        $this->instance->append('LIMIT', 10, 'OFFSET', 0);

        $this->assertEquals('LIMIT 10 OFFSET 0', $this->instance->get());
    }

    public function testAppendKeepsAZeroString(): void
    {
        $this->instance->append('0');

        $this->assertTrue($this->instance->has());
        $this->assertEquals('0', $this->instance->get());
    }

    public function testAppendKeepsFalseOutButNotZero(): void
    {
        // false stringifies to '', true to '1' - only the empty one is dropped
        $this->instance->append(false, true, 0);

        $this->assertEquals('1 0', $this->instance->get());
    }

    public function testAppendDropsEmptyStrings(): void
    {
        $this->instance->append('a', '', 'b');

        $this->assertEquals('a b', $this->instance->get());
    }

    public function testAppendDropsWhitespaceOnlyWhenAutoTrimming(): void
    {
        // trimming '   ' leaves nothing to add, so it must not become an
        // empty entry - that would double up the separator in get()
        $this->instance->append('a', '   ', 'b');

        $this->assertEquals('a b', $this->instance->get());
    }

    public function testAppendKeepsWhitespaceWhenNotAutoTrimming(): void
    {
        $builder = new StringBuilder(',', false);

        $builder->append('a', ' ', 'b');

        $this->assertEquals('a, ,b', $builder->get());
    }

    public function testAppendTrimsByDefault(): void
    {
        $this->instance->append('  a  ', '  b  ');

        $this->assertEquals('a b', $this->instance->get());
    }

    public function testGetWrapsWithAPrefixAndSuffix(): void
    {
        $this->instance->append('a', 'b');

        $this->assertEquals('(a b)', $this->instance->get('(', ')'));
    }

    public function testGetTakesASeparatorOverride(): void
    {
        $this->instance->append('a', 'b');

        $this->assertEquals('a,b', $this->instance->get('', '', ','));
    }

    public function testGetIfHasReturnsEmptyWhenNothingWasAppended(): void
    {
        $this->assertEquals('', $this->instance->getIfHas('LIMIT '));
    }

    public function testGetIfHasWrapsWhenSomethingWasAppended(): void
    {
        $this->instance->append(0);

        // a lone zero still counts as content
        $this->assertEquals('LIMIT 0', $this->instance->getIfHas('LIMIT '));
    }

    public function testHasReflectsContent(): void
    {
        $this->assertFalse($this->instance->has());

        $this->instance->append('');

        $this->assertFalse($this->instance->has());

        $this->instance->append(0);

        $this->assertTrue($this->instance->has());
    }

    public function testClearEmptiesTheBuilder(): void
    {
        $this->instance->append('a', 'b');

        $this->assertInstanceOf(StringBuilder::class, $this->instance->clear());
        $this->assertFalse($this->instance->has());
        $this->assertEquals('', $this->instance->get());
    }

    public function testSeparatorChangesTheJoin(): void
    {
        $this->assertInstanceOf(StringBuilder::class, $this->instance->separator(', '));

        $this->instance->append('a', 'b');

        $this->assertEquals('a, b', $this->instance->get());
    }
}
