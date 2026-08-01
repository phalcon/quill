namespace Phalcon\Sample;

/**
 * Pulls the Sample trait in, so the reader has a use-trait node to find and
 * the registry has an inverse edge to build.
 */
class Consumer
{
    use Sample;
}
