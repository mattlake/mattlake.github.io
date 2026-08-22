---
title: "Why I Am Writing a Game From Scratch in Go"
date: 2026-08-22
tags: ["go", "gamedev"]
summary: "Leaving the engine, the framework and the AI code generation behind to build a puzzle platformer by hand, and what Go's value semantics taught me in the first week."
---

My day job is as a tech lead at a communications company, working in .NET and
Angular. Fairly standard stuff for a large business developer. Most of the work
is web dev on a legacy system: add features, restyle some elements, hook up new
external APIs. Most days are digital plumbing rather than solving problems.

Since AI arrived in our workflow I very rarely write any code by hand any more,
and I suspect a lot of developers feel the same way about that as I do. Writing
the code and solving the problems was always the fun part, and it has been the
fun part since I first typed a line into a Commodore 64 in primary school.

So I have decided to do the thing that most developers think about at some point,
and build a game. I came up with the idea with my nine year old daughter, and
more details will follow, but it is going to be a puzzle platformer involving a
chameleon.

I also wanted an excuse to work at a lower level than I am used to. Modern web
development rarely makes you think about memory or performance, and I have got to
the point where I would quite like to know what a cache line actually is, and why
it should change the way I lay out my data.

So here we are. I am going to write a game, from scratch, in Go.

## What I mean by "from scratch"

Not literally everything. I am using GLFW to open a window and OpenGL to talk to
the GPU, because writing my own Cocoa and Win32 window handling would teach me a
lot about window handling and very little about games.

The line I have settled on is that OpenGL is the hardware interface. It sits at
roughly the same level as writing pixels into a framebuffer, it is not an engine,
and it does not make any decisions on my behalf. Everything above it I am writing
myself, which means the shaders, the mesh loading, the camera maths, the draw call
batching, the fixed timestep loop, the collision, and the puzzle logic.

## Why not use an engine like Godot or Unity?

I have played with both of these before, and for what I am trying to do here they
solve a bit too much. They are excellent pieces of software that handle a lot of
boilerplate and a lot of genuinely difficult problems, but the difficult problems
are the fun bits, at least for me, and they are also where I have the most to
learn.

MonoGame is the interesting exception, because it is a framework rather than an
engine, and it already leaves you most of the hard parts to solve yourself. I
have used it before and it would have been a perfectly reasonable choice here. I
just want to go one layer below where it starts.

## Why Go?

Most of the game development I see is done in C# or C++. My day job is C#, so a
change of pace appealed to me. Go is also a reasonable paradigm shift, with no
classes and no inheritance, using composition and interfaces instead.

I originally wrote that Go was "closer to the metal" than C#, and then realised
that this is not really true. Both languages are garbage collected, and C# will
give you pointers if you go looking for them. The actual difference turns out to
be more interesting than the one I thought I was describing.

A Go struct is a value, whereas a C# class is always a reference, and that single
difference has caught me out twice already. A method with a value receiver
quietly mutates a copy, and the compiler does not say a word about it. Then
`for i, entity := range entities` hands you a copy of each struct rather than the
struct itself, so my movement code happily ran for a full simulated second while
nothing on screen moved.

Go will also tell you what the compiler decided, which I was not expecting.
Running `go build -gcflags=-m` prints out every choice it made about what lives on
the stack and what escapes to the heap. The .NET JIT is making the same sorts of
decisions at runtime and never mentions any of them.

So it is not that Go is closer to the metal. It is that the memory layout is mine
to get wrong.

And, why not Go?

## Am I using AI?

Yes, but not to write the code.

I am writing every line myself. What I am using it for is explaining the things I
do not know yet, reviewing what I have written, and arguing with me when I have
made a bad call. That has been genuinely useful, and it has also been wrong a
couple of times in ways that I only caught because I understood the code well
enough to check it.

Tony Stark had the right of it in Spider-Man: Homecoming, when he told Peter
Parker that "if you're nothing without this suit, then you shouldn't have it".
Asking Claude to solve all of the problems for me would defeat the entire point
of the exercise, but refusing to let it explain a concept to me would just be
posturing.

## One thing I have learned already

Go deliberately randomises the iteration order of a map. Every time you range
over one you get a different order, and this is not an accident or an
implementation detail that might change, it is a deliberate design decision.

I knew that in the abstract, but I had not thought through what it does to a game
simulation. Floating point addition is not associative, so the order you add a
set of numbers in can change the answer you get. I summed the same five floats in
map order two thousand times in a single process, and got two different totals,
1269 runs one way and 731 the other.

So maps are out, or at least ranging over them is, anywhere that affects the
simulation. Looking a value up by key is still perfectly fine, it is the
iteration that has to go, and the entities have ended up living in slices
instead.

The reason this matters more to me than it otherwise might is that I want the
game to be reproducible. Given the same starting state and the same sequence of
inputs, it should produce exactly the same result every time it runs. One map
range in the wrong place is enough to break that, and it will break it quietly,
in roughly one run out of every three.

I will get into how I am testing for that in the next post, along with why the
game runs at 64 ticks per second rather than the more obvious 60.
