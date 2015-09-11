<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Twig_Tests_EnvironmentTest extends PHPUnit_Framework_TestCase
{
    public function testAutoescapeOption()
    {
        $loader = new Twig_Loader_Array(array(
            'html' => '{{ foo }} {{ foo }}',
            'js' => '{{ bar }} {{ bar }}',
        ));

        $twig = new Twig_Environment($loader, array(
            'debug' => true,
            'cache' => false,
            'autoescape' => array($this, 'escapingStrategyCallback'),
        ));

        $this->assertEquals('foo&lt;br/ &gt; foo&lt;br/ &gt;', $twig->render('html', array('foo' => 'foo<br/ >')));
        $this->assertEquals('foo\x3Cbr\x2F\x20\x3E foo\x3Cbr\x2F\x20\x3E', $twig->render('js', array('bar' => 'foo<br/ >')));
    }

    public function escapingStrategyCallback($filename)
    {
        return $filename;
    }

    public function testGlobals()
    {
        // globals can be added after calling getGlobals
        $twig = new Twig_Environment($this->getMock('Twig_LoaderInterface'));
        $twig->addGlobal('foo', 'foo');
        $twig->getGlobals();
        $twig->addGlobal('foo', 'bar');
        $globals = $twig->getGlobals();
        $this->assertEquals('bar', $globals['foo']);

        // globals can be modified after runtime init
        $twig = new Twig_Environment($this->getMock('Twig_LoaderInterface'));
        $twig->addGlobal('foo', 'foo');
        $twig->getGlobals();
        $twig->initRuntime();
        $twig->addGlobal('foo', 'bar');
        $globals = $twig->getGlobals();
        $this->assertEquals('bar', $globals['foo']);

        // globals can be modified after extensions init
        $twig = new Twig_Environment($this->getMock('Twig_LoaderInterface'));
        $twig->addGlobal('foo', 'foo');
        $twig->getGlobals();
        $twig->getFunctions();
        $twig->addGlobal('foo', 'bar');
        $globals = $twig->getGlobals();
        $this->assertEquals('bar', $globals['foo']);

        // globals can be modified after extensions and runtime init
        $twig = new Twig_Environment($loader = new Twig_Loader_Array(array('index' => '{{foo}}')));
        $twig->addGlobal('foo', 'foo');
        $twig->getGlobals();
        $twig->getFunctions();
        $twig->initRuntime();
        $twig->addGlobal('foo', 'bar');
        $globals = $twig->getGlobals();
        $this->assertEquals('bar', $globals['foo']);

        $twig = new Twig_Environment($loader);
        $twig->getGlobals();
        $twig->addGlobal('foo', 'bar');
        $template = $twig->loadTemplate('index');
        $this->assertEquals('bar', $template->render(array()));

        // globals cannot be added after runtime init
        $twig = new Twig_Environment($this->getMock('Twig_LoaderInterface'));
        $twig->addGlobal('foo', 'foo');
        $twig->getGlobals();
        $twig->initRuntime();
        try {
            $twig->addGlobal('bar', 'bar');
            $this->fail();
        } catch (LogicException $e) {
            $this->assertFalse(array_key_exists('bar', $twig->getGlobals()));
        }

        // globals cannot be added after extensions init
        $twig = new Twig_Environment($this->getMock('Twig_LoaderInterface'));
        $twig->addGlobal('foo', 'foo');
        $twig->getGlobals();
        $twig->getFunctions();
        try {
            $twig->addGlobal('bar', 'bar');
            $this->fail();
        } catch (LogicException $e) {
            $this->assertFalse(array_key_exists('bar', $twig->getGlobals()));
        }

        // globals cannot be added after extensions and runtime init
        $twig = new Twig_Environment($this->getMock('Twig_LoaderInterface'));
        $twig->addGlobal('foo', 'foo');
        $twig->getGlobals();
        $twig->getFunctions();
        $twig->initRuntime();
        try {
            $twig->addGlobal('bar', 'bar');
            $this->fail();
        } catch (LogicException $e) {
            $this->assertFalse(array_key_exists('bar', $twig->getGlobals()));
        }

        // test adding globals after initRuntime without call to getGlobals
        $twig = new Twig_Environment($this->getMock('Twig_LoaderInterface'));
        $twig->initRuntime();
        try {
            $twig->addGlobal('bar', 'bar');
            $this->fail();
        } catch (LogicException $e) {
            $this->assertFalse(array_key_exists('bar', $twig->getGlobals()));
        }
    }

    public function testCompileSourceInlinesSource()
    {
        $twig = new Twig_Environment($this->getMock('Twig_LoaderInterface'));

        $source = "<? /*foo*/ ?>\r\nbar\n";
        $expected = "/* <? /*foo*//* ?>*/\n/* bar*/\n/* */\n";

        $this->assertContains($expected, $twig->compileSource($source, 'index'));
    }

    public function testExtensionsAreNotInitializedWhenRenderingACompiledTemplate()
    {
        $cache = new Twig_Cache_Filesystem($dir = sys_get_temp_dir().'/twig');
        $options = array('cache' => $cache, 'auto_reload' => false, 'debug' => false);

        // force compilation
        $twig = new Twig_Environment($loader = new Twig_Loader_Array(array('index' => '{{ foo }}')), $options);

        $key = $cache->generateKey($twig->getTemplateClass('index'), $twig->getTemplateClassPrefix());
        $cache->write($key, $twig->compileSource('{{ foo }}', 'index'));

        // check that extensions won't be initialized when rendering a template that is already in the cache
        $twig = $this
            ->getMockBuilder('Twig_Environment')
            ->setConstructorArgs(array($loader, $options))
            ->setMethods(array('initExtensions'))
            ->getMock()
        ;

        $twig->expects($this->never())->method('initExtensions');

        // render template
        $output = $twig->render('index', array('foo' => 'bar'));
        $this->assertEquals('bar', $output);

        unlink($key);
    }

    public function testAddExtension()
    {
        $twig = new Twig_Environment($this->getMock('Twig_LoaderInterface'));
        $twig->addExtension(new Twig_Tests_EnvironmentTest_Extension());

        $this->assertArrayHasKey('test', $twig->getTags());
        $this->assertArrayHasKey('foo_filter', $twig->getFilters());
        $this->assertArrayHasKey('foo_function', $twig->getFunctions());
        $this->assertArrayHasKey('foo_test', $twig->getTests());
        $this->assertArrayHasKey('foo_unary', $twig->getUnaryOperators());
        $this->assertArrayHasKey('foo_binary', $twig->getBinaryOperators());
        $this->assertArrayHasKey('foo_global', $twig->getGlobals());
        $visitors = $twig->getNodeVisitors();
        $this->assertEquals('Twig_Tests_EnvironmentTest_NodeVisitor', get_class($visitors[2]));
    }
}

class Twig_Tests_EnvironmentTest_Extension extends Twig_Extension
{
    public function getTokenParsers()
    {
        return array(
            new Twig_Tests_EnvironmentTest_TokenParser(),
        );
    }

    public function getNodeVisitors()
    {
        return array(
            new Twig_Tests_EnvironmentTest_NodeVisitor(),
        );
    }

    public function getFilters()
    {
        return array(
            new Twig_Filter('foo_filter'),
        );
    }

    public function getTests()
    {
        return array(
            new Twig_Test('foo_test'),
        );
    }

    public function getFunctions()
    {
        return array(
            new Twig_Function('foo_function'),
        );
    }

    public function getOperators()
    {
        return array(
            array('foo_unary' => array()),
            array('foo_binary' => array()),
        );
    }

    public function getGlobals()
    {
        return array(
            'foo_global' => 'foo_global',
        );
    }

    public function getName()
    {
        return 'environment_test';
    }
}

class Twig_Tests_EnvironmentTest_TokenParser extends Twig_TokenParser
{
    public function parse(Twig_Token $token)
    {
    }

    public function getTag()
    {
        return 'test';
    }
}

class Twig_Tests_EnvironmentTest_NodeVisitor implements Twig_NodeVisitorInterface
{
    public function enterNode(Twig_Node $node, Twig_Environment $env)
    {
        return $node;
    }

    public function leaveNode(Twig_Node $node, Twig_Environment $env)
    {
        return $node;
    }

    public function getPriority()
    {
        return 0;
    }
}
