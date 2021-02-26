# Shopware 6 Aggregation Key Cache Generation Bug

This plugin demonstrates the problem that the cache key for aggregations is the same even if the requested ids for a 
Criteria have changed. This leads to undefined behaviour when requesting aggregations from the database.

```php
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
```

Output:
```
$ ./bin/console wesiocachebug:example
---------------------- EXPERIMENT 1 ----------------------
We expect 0 results here as the id is non existent! Results: 0
---------------------- EXPERIMENT 2 ----------------------
Now we run the same query, this time with a product id we know will exist!
We expect 1 results here as we know the ID must exist!! Results: 0
What happend here? Well the result from before got cached and the cache returned the same result as before!!
---------------------- EXPERIMENT 3 ----------------------
Let's keep everything the same but change the name of the aggregation
We expect 1 results here as we know the ID must exist!! Results: 1
Now we got the correct result!!
```
