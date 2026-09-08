---
title: "What Writing an Entity System in Go Taught Me About C#"
slug: "what-writing-an-entity-system-in-go-taught-me-about-csharp"
aliases:
  - "/blog/what-writing-an-entity-system-in-go-taught-me-about-c#/"
  - "/blog/what-writing-an-entity-system-in-go-taught-me-about-c%23/"
date: 2026-09-05
tags: ["go", "gamedev"]
summary: "A pointer to an entity seemed like the obvious thing to hand out. It was not, and working out why sent me back to the C# documentation rather than the Go."
---

Everything in the world of my game is an entity. Walls, doors, levers, the player, anything that has a position and might one day move. They all live in a single slice, which they ended up in because ranging over a map in Go gives you a different order every time, and I want the simulation to be reproducible.

That part I had already written about. What I had not thought about at all was how anything outside the store refers to one of those entities once it is in there, and it turns out quite a lot of things need to. The world needs to know which entity is the player. A door needs to know which lever opens it. Something has to hold on to something.

In c# I would hold the object without a second thought. But this is not c#.

## So what is wrong with a pointer?

I did the obvious thing to start with, which was to hand back a pointer to the entity.

```go
func (e *EntityStore) Get(index int) *Entity {
    return &e.entities[index]
}
```

This works great, right up until the store grows.

I spawned an entity and stored the pointer as a variable, I then spawned a load more entities to the point that the containing slice needed to grow. This is the point my c# experience bit me, and it is down to the fundamental differences between a `List` in c# and a slice in Go.

A list in c# is a collection or list of references to memory blocks. These blocks do not need to be contiguous, it is just a list of pointers to where the bytes of data really are. When you add to the list it just appends a new reference. Go is different. In Go a slice is a block of contiguous memory that actually holds the bytes of data. When this needs to grow then a few things happen under the hood. A new larger array is created in a new location, the data is copied across and the old header is pointed at the new array. Once the original slice is no longer referenced anywhere the garbage collector does what it does.

How did this burn me? I'll show you.

```go
entity := &entities[0]
entity.changed = false

// Some action here that grows, and thus moves the slice in memory

entity.changed = true

// Later we grab the first entity again
entity2 := &entities[0]

// It would be a safe assumption that entity and entity2 are pointing at the same data, but...
fmt.Print(entity2.changed) // would print false. We updated an entity that had since moved.
```

My pointer was still happily pointing into an abandoned array, reading and writing to an entity that still existed. It had not been collected, because my variable was still referencing it.

Any new reads would grab the entity from the new location and not see the updates.

## So why does the c# not hurt me like this?

The .NET garbage collector does move objects around fairly aggressively, since compacting the heap is most of what a collection is for. However, in c# moving the data around is only half of the job it does. In the second phase, which Microsoft calls 'the relocating phase', the references to objects that are compacted are updated. So the garbage collector moves data about and then updates all references to them so that a reference you took 10 mins ago still points to the thing you think it does.

It is this second phase that Go does not do, which I learnt the hard way. I have never had to think about this before, which is kinda the point I suppose. A reference in c# stays valid for as long as the object exists and the runtime does whatever work it takes to keep that true.

## A handle is just two numbers

What I do instead is hand out identity rather than a reference, which in practice means a spawn gives you this.

```go
type Handle struct {
	Index      uint32
	Generation uint32
}
```

The index is a position in the store, and the store is four slices running side by side.

```go
type EntityStore struct {
    entities    []Entity
    generations []uint32
    free        []uint32
    alive       []bool
}
```

One entry each per slot, lined up so that a handle's index picks the same position in all four.

I would have put the generation and the alive flag on the entity itself in c#, and I did that first. It grew the struct from 32 bytes to 40, because five bytes of new data rounds up to eight once Go has aligned everything, and it meant that checking whether a slot was alive pulled a position and a velocity into cache to read one byte. Moving the flags into their own slice fixed it.

Just having an index is not enough. I want to avoid deleting from the slice to maintain index stability, so an entity in any slot may be replaced at any time with a new one. In this case the entity in slot x is no longer the entity that any handle expects. Instead of an error you just get an unexpected entity. So the store keeps a generation for each slot, a handle carries a copy of whatever that generation was when it was created, and checking whether a handle is still any good is just comparing the two.

```go
func (e *EntityStore) Despawn(handle Handle) bool {
	if !e.IsAlive(handle) {
		return false
	}

	e.alive[handle.Index] = false
	e.generations[handle.Index]++
	e.free = append(e.free, handle.Index)
	return true
}
```

The part I like about this is that bumping the generation is a single write that invalidates every outstanding handle to that slot at once without worrying about any open references to it, making despawning cheap.

Bumping the generation on despawn rather than spawn is important though, as I found out by getting backwards initially. It looks equivalent but it meant that in the window between despawn and the slot being reused any open references could still grab the now despawned entity.

## So why can I not just delete it?

You may think that the obvious way to despawn something is to simply take it out of the slice, and Go gives you `slices.Delete` to do exactly that. It shifts every element after the removal point down by one, so pull out index 3 and what was at 4 is now at 3, and so on all the way down. There is nothing surprising in that, and c# does the exact same thing. Both languages shuffle everything down and both of them are O(n) while they do it.

The generations that I added do not fix this. Any handle holding an index higher than that of a deleted entity is now holding the index for the wrong entity. Nothing was despawned in any of those slots, so no generations are bumped, so all of those handles pass my liveness check and read the wrong entity with a smile. The generation check does not prevent this as it sees no change.

I have not run into this in c# because I was never holding a position, I was holding the object itself. When I write `parts.First(p => p.Id == 1534)` I get back a `Part`, and that is a reference to the object rather than its position in the list. The list can be reordered, half of it can be removed, and my reference still finds the same object, because the object never moved. Only the list changed. This goes back to a fundamental difference between Go's slices and c#'s lists, slices hold the entities while lists hold the references to the entities.

So the rule is that an entity's index can never change for as long as it lives. No deleting, no sorting, no compacting the slice to tidy it up when it is full of holes. Despawning leaves the slot exactly where it is, marks it dead, bumps its generation, and pushes the index onto a list of slots that can be reused later.

The price of that is holes. A world that has been running for a while is a slice with gaps in it, and those gaps cost me speed every time I iterate through it, mostly because the CPU cannot predict which slots it is about to skip.

## The list of free slots

Looking at the `EntityStore` struct you may have spotted that `free` and `alive` essentially say the same thing twice, a free slot is not alive and an alive slot is not free. There is an element of duplication here but they earn their place based on the question they are answering.

To answer "Is slot 7 alive?" I check `alive[7]`, which is O(1). I could also iterate through `free` but that is O(n).

To answer "Give me a free slot" I just pop the last off `free`, which is O(1), rather than iterate through `alive` until I find a false value, which is O(n).

There is a complexity cost when it comes to writes but when handling a large number of entities the performance payoff is worth it.

I did get one thing wrong here. I preallocated the free list to the same capacity as the entities list, on the assumption that the two would grow together but they do not. The free list only ever holds the slots that are dead right now, so in a world where nothing has been despawned it stays empty however many entities there are. I only spotted it while benchmarking the store at a million entities, where I had reserved 3.8MB for a list whose length was still zero. That is nowhere near the scale I am actually building for, and at a few hundred entities the wasted memory would not matter in the slightest, but the reasoning behind it was wrong and that would not have got better on its own. Now I let it grow as it needs to.

## What it actually cost

Handles are more awkward than pointers, I can't deny that. Every read requires a few checks rather than just having the entity, the store has to be passed around to do anything useful because I am not passing around entities, and a handle sitting in a debugger tells you nothing without looking it up.

What I get for that is resilience rather than a potential trap that I need to avoid. Two views of one entity disagreeing about where it is cannot happen now, because there is only ever one view of it. A stale handle does not read the wrong entity, it fails a comparison and tells me it is stale.

## So what did Go teach me about c#?

A project that I started with the intention of learning Go is ironically teaching me more about c# than I knew before. c# solves a lot of problems for me day to day without me ever having to think about how. As I have been solving these problems in Go I have always asked why this is not a problem for me in c#, which in turn leads me to the docs and gaining a more fundamental understanding of the language I use every day.
