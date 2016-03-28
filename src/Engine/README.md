# Airship Engine 

**Don't edit any of these files!**

Your changes will get erased by the automatic update process.

Instead, if you need to change the behavior of an Engine part, use the `Gears`
API.

```php
<?php
namespace Airship\Gears\Nikic\FastRoute;

\Airship\Engine\Gears::extract('AutoPilot', 'AutoPilotShim', __NAMESPACE__);

class Simple extends AutoPilotShim
{
    /**
     * Actually serve the HTTP request
     */
    public function route()
    {
        // Hook into nikic/fastroute instead, for example
    }
}

\Airship\Engine\Gears::attach('AutoPilot', 'Simple', __NAMESPACE__);
```

