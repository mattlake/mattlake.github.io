---
extends: _layouts.post
section: content
title: Using scoped services in Background / Hosted Services (C#)
date: 2023-02-13
description: How to use scoped services in background services and other singletons.
cover_image: /assets/img/posts/code_inspection_header.svg
featured: true
---

I have recently been creating a few background services/hosted services in my .NET apps to use message consumers. Something I have come across a few times is the ability to use scoped services, such as Entity Framework contexts, in these services as they are registered as singletons. All code related to this article can be found [on github here](#https://github.com/mattlake/ScopingExample)

## Let's look at an example of the issue.
Here we have a simple hosted service that gets the first User from the database and logs their details to the console every minute. This is not much use as an actual service but it helps us illustrate the issue.

We use DI to bring in the DB context in the constructor

```
private readonly ILogger<BrokenWorker> _logger;
private readonly DataContext _context;

public BrokenWorker(ILogger<BrokenWorker> logger, DataContext dataContext)
{
    _logger = logger;
    _context = dataContext;
}
```

And in the execute async method we attempt to get the user and log the details.

```
protected override async Task ExecuteAsync(CancellationToken stoppingToken)
{
    while (!stoppingToken.IsCancellationRequested)
    {
        User user = _context.Users.FirstOrDefault();

        _logger.LogInformation($"Id: {user.UserId}, username: {user.UserName}");

        await Task.Delay(TimeSpan.FromMinutes(1), stoppingToken);
    }
}
```

However, if we try to compile and run this code then we get an exception

```
System.InvalidOperationException: Error while validating the service descriptor 'ServiceType: Microsoft.Extensions.Hosting.IHostedService Lifetime: Singleton ImplementationType: ScopingExample.Worker': Cannot consume scoped service 'DbContext.Context.DataContext' from singleton 'Microsoft.Extensions.Hosting.IHostedService'
```

## So what is the issue?
The exception thrown here is very explicit about what went wrong, thankfully, singletons cannot consume scoped services.  The reason the exception gets thrown is that the DI container wants to prevent you from creating captive dependencies.

## So how do we use scoped services in singletons?
The solution lies in using the IServiceScopeFactory to create a new scope manually. To do this we first change the constructor to resolve a scope factory from the DI container.

```
private readonly ILogger<BrokenWorker> _logger;
private readonly IServiceScopeFactory _scopeFactory;

public WorkingWorker(ILogger<BrokenWorker> logger, IServiceScopeFactory scopeFactory)
{
    _logger = logger;
    _scopeFactory = scopeFactory;
}
```

Then in our execute method, we create a new scope and manually resolve our dependencies.

```
protected override async Task ExecuteAsync(CancellationToken stoppingToken)
{
    while (!stoppingToken.IsCancellationRequested)
    {
        using (IServiceScope scope = _scopeFactory.CreateScope())
        {
            DataContext? dataContext = scope.ServiceProvider.GetService<DataContext>();
            User user = dataContext.Users.FirstOrDefault();

            _logger.LogInformation($"Id: {user.UserId}, username: {user.UserName}");
        }

        await Task.Delay(TimeSpan.FromMinutes(1), stoppingToken);
    }
}
```

And now everything will build and run.

## Word of warning
Generally speaking, using scoped services in a singleton service is a bad practice and can lead to bugs and memory leaks. If you can refactor to avoid it, you probably should, but in certain instances, like background services, it can be unavoidable.