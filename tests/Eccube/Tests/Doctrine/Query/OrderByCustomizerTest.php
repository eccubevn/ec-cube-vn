<?php


namespace Eccube\Tests\Doctrine\Query;


use Doctrine\ORM\QueryBuilder;
use Eccube\Doctrine\Query\OrderByClause;
use Eccube\Doctrine\Query\OrderByCustomizer;
use Eccube\Tests\EccubeTestCase;

class OrderByCustomizerTest extends EccubeTestCase
{

    public function setUp()
    {
        parent::setUp();
    }

    public function testCustomizeNop()
    {
        $builder = $this->createQueryBuilder();
        $customizer = new OrderByCustomizerTest_Customizer(function() { return []; });
        $customizer->customize($builder, null, '');

        self::assertEquals('SELECT p FROM Product p', $builder->getDQL());
    }

    public function testCustomizeNop_Should_not_Override()
    {
        $builder = $this->createQueryBuilder()
            ->orderBy('name', 'desc');
        $customizer = new OrderByCustomizerTest_Customizer(function() { return []; });
        $customizer->customize($builder, null, '');

        self::assertEquals('SELECT p FROM Product p ORDER BY name desc', $builder->getDQL());
    }

    public function testCustomize_Override()
    {
        $builder = $this->createQueryBuilder()
            ->orderBy('name', 'desc');
        $customizer = new OrderByCustomizerTest_Customizer(function() { return [
            new OrderByClause('productId')
        ]; });
        $customizer->customize($builder, null, '');

        self::assertEquals('SELECT p FROM Product p ORDER BY productId asc', $builder->getDQL());
    }

    public function testCustomize_Override_with_multi_clause()
    {
        $builder = $this->createQueryBuilder()
            ->orderBy('name', 'desc');
        $customizer = new OrderByCustomizerTest_Customizer(function() { return [
            new OrderByClause('productId'),
            new OrderByClause('name', 'desc')
        ]; });
        $customizer->customize($builder, null, '');

        self::assertEquals('SELECT p FROM Product p ORDER BY productId asc, name desc', $builder->getDQL());
    }

    /**
     * @return QueryBuilder
     */
    private function createQueryBuilder()
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from('Product', 'p');
    }
}

class OrderByCustomizerTest_Customizer extends OrderByCustomizer
{

    /**
     * @var callable $closure
     */
    private $closure;

    function __construct($closure)
    {
        $this->closure = $closure;
    }

    /**
     * @param array $params
     * @param $queryKey
     * @return OrderByClause[]
     */
    public function createStatements($params, $queryKey)
    {
        $callback = $this->closure;
        return $callback($params);
    }

    /**
     * カスタマイズ対象のキーを返します。
     *
     * @return string
     */
    public function getQueryKey()
    {
        return '';
    }
}