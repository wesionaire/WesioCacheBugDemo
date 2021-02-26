<?php


namespace WesioCacheBugDemo\Command;


use Shopware\Core\Content\Content;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleCommand extends Command
{
    protected static $defaultName = 'wesiocachebug:example';
    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function __construct(EntityRepositoryInterface $productRepository)
    {
        parent::__construct();
        $this->productRepository = $productRepository;
    }

    private function getAggregationProductCount($productId, $aggregationName)
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria([$productId]);
        $criteria->addAggregation(new CountAggregation($aggregationName, 'product.id'));
        return $this->productRepository->search($criteria, $context)
            ->getAggregations()
            ->get($aggregationName);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $context = Context::createDefaultContext();
        // Shopware generates random products each time it's installed
        // => we get one existent product id
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $productId = $this->productRepository->searchIds($criteria, $context)->firstId();

        $output->writeln("---------------------- EXPERIMENT 1 ----------------------");
        // Let's first warm up the cache
        // We'll send a query where the productCount will be 0
        $aggregationResult = $this->getAggregationProductCount('00000000000000000000000000000000', 'testName1');
        $output->writeln("We expect 0 results here as the id is non existent! Results: " . $aggregationResult->getCount());

        $output->writeln("---------------------- EXPERIMENT 2 ----------------------");
        // Now we query a different product (see Criteria([$productId]))
        $output->writeln("Now we run the same query, this time with a product id we know will exist!");
        $aggregationResult = $this->getAggregationProductCount($productId, 'testName1');
        $output->writeln("We expect 1 results here as we know the ID must exist!! Results: " . $aggregationResult->getCount());
        $output->writeln("What happend here? Well the result from before got cached and the cache returned the same result as before!!");

        // Now we fire the same query we just change productCountA to productCountB
        $output->writeln("---------------------- EXPERIMENT 3 ----------------------");
        $output->writeln("Let's keep everything the same but change the name of the aggregation");
        $aggregationResult = $this->getAggregationProductCount($productId, 'testName2'); // changed the name!
        $output->writeln("We expect 1 results here as we know the ID must exist!! Results: " . $aggregationResult->getCount());

        $output->writeln("Now we got the correct result!!");
    }
}
