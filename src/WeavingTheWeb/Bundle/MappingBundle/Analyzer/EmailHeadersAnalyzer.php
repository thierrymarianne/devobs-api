<?php

namespace WeavingTheWeb\Bundle\MappingBundle\Analyzer;

use WeavingTheWeb\Bundle\MappingBundle\Entity\Property;

/**
 * @package WeavingTheWeb\Bundle\MappingBundle\Analyzer
 */
class EmailHeadersAnalyzer 
{
    /**
     * @var \Doctrine\ORM\EntityManager $entityManager
     */
    public $entityManager;

    /**
     * @var \WeavingTheWeb\Bundle\MappingBundle\Parser\EmailHeadersParser $parser
     */
    public $parser;

    /**
     * @var \Psr\Log\LoggerInterface $logger
     */
    public $logger;

    /**
     * @param $options
     * @return array
     */
    public function analyze(array $options)
    {
        $emailHeadersProperties = $this->aggregateEmailHeadersProperties($options);
        $this->saveEmailsHeadersAsProperties($emailHeadersProperties);

        return $emailHeadersProperties;
    }

    /**
     * @param $options
     * @return array
     */
    protected function aggregateEmailHeadersProperties($options)
    {
        $entityManager = $this->entityManager;
        /** @var \WeavingTheWeb\Bundle\Legacy\ProviderBundle\Repository\WeavingHeaderRepository $headerRepository */
        $headerRepository = $entityManager->getRepository('WeavingTheWebLegacyProviderBundle:WeavingHeader');

        $emailHeadersProperties = array();
        while ($options['offset'] <= $options['max_offset']) {
            $headers = $headerRepository->paginate($options['offset'], $options['items_per_page']);

            foreach ($headers as $header) {
                $properties = $this->parser->parse($header['hdrValue']);

                foreach ($properties as $name => $value) {
                    $emailHeadersProperties[$name] = $value;
                }

                $this->logger->info(
                    sprintf(
                        '%d have been parsed',
                        count($emailHeadersProperties)
                    )
                );
            }

            $options['offset']++;
            $this->logger->info(
                sprintf(
                    'Moving selection cursor with offset set at %d',
                    $options['offset']
                )
            );
        }

        return $emailHeadersProperties;
    }

    /**
     * @param $emailHeadersProperties
     */
    protected function saveEmailsHeadersAsProperties($emailHeadersProperties)
    {
        /** @var \Doctrine\ORM\EntityRepository $propertyRepository */
        $propertyRepository = $this->entityManager->getRepository('WeavingTheWebMappingBundle:Property');
        foreach ($emailHeadersProperties as $name => $value) {
            $header = $propertyRepository->findOneBy(['name' => $name]);
            if (is_null($header)) {
                $property = new Property();
                $property->setName($name);
                $property->setType($property::TYPE_EMAIL_HEADER);

                $this->entityManager->persist($property);
            }
        }
        $this->entityManager->flush();
    }
} 