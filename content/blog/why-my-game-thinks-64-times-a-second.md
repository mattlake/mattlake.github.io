---
title: "Why My Game Thinks 64 Times a Second"
date: 2026-08-26
tags: ["go", "gamedev"]
summary: "Letting the player's computer decide how fast the game runs causes problems. Picking my own rate turned out to be more interesting than I expected."
---

In my last post I said that this post would cover testing, and why my tick rate is 64 rather than 60. The testing framework is not finished, so I will cover that when it is complete. The tick rate I can do.

The short version is that at 60 ticks a second the length of a tick has to be written down two different ways, and the two do not quite agree. At 64 they do, and I did not fancy maintaining both. The longer version is below.

I want the game reproducible, meaning the same inputs always give the same result. It is a puzzle game, so nothing needs to be random.

## Why fix the tick rate at all?

A quick history lesson...

In 1978 a new game became a hit in arcades around the world, Space Invaders. For those living under rocks, you the player took cover behind barriers at the bottom of the screen, dodging incoming fire from aliens descending from the top. As you shot and destroyed the aliens they would speed up, increasing the difficulty as the level progressed. That difficulty curve was a happy accident.

The processor in the cabinets (Intel 8080) was fairly limited and could only redraw so many sprites at a time. As the number of aliens dropped so did the number of sprites needing processing, so the operation finished faster. The creator, Tomohiro Nishikado, spotted it in testing and kept it. It could be argued that this unintentional behaviour made the game. More modern remakes fake the acceleration, as the hardware is no longer a limitation.

Space Invaders got away with it because every cabinet had the same processor running at the same speed. Nobody playing at home gets that. Machines differ, and one machine is not even steady with itself. Frame rates drop when the player walks into a crowded room, or the graphics card gets hot.

My game will have guard patrols and sweeping vision cones. Players will need to watch for patterns and time their movements correctly for success. If the game only checks whether a guard can see you once per frame, then a player whose machine draws more frames gets more checks, and gets spotted more often. The same hiding place works on one computer and not on another.

## Reinventing the clock

The tick rate is the speed, or frequency, at which a game runs, or thinks. It has nothing to do with frames per second, which I had muddled up originally myself. A decision I needed to make early was what speed I wanted my game to run at. What was my desired tick rate?

I had no idea, so I started by looking at what other games and engines use.

```text
Doom                      35 a second
Unity, by default         50 a second
Godot, by default         60 a second
Counter-Strike 2          64 a second
Valorant                 128 a second
```

No correct or obvious answer there. So why not go as high as possible? Because 'thinking' more often costs processor time: twice the tick rate, twice the work. What twice the tick rate also gives you is twice the precision. With the vision cone I mentioned, the higher the tick rate the less distance it moves between checks. With a slow tick rate the cone may pass over the player completely between checks.

For my application a wide range of tick rates would work. For a few guards and a few hundred things on screen, anywhere from 30 to 128 costs me and the player nothing that would ever be noticed.

I have previously toyed with Godot, which uses 60, so why not use that? 60 is not wrong, but that does not mean it is right either, and I could not find a technical reason to commit to it. I did start at 60, but as I started writing some of the maths for updates I ran into things I just did not like. I settled on 64 for reasons I will come back to.

## Catching up

Frames still arrive whenever they are ready, so for smooth gameplay something has to sit between the frames and the ticks. Add up the real time as it goes by, and every time that total reaches a full tick, run one and take it off the total. Whatever is left waits for next time. That running total is called an accumulator.

A tick in my game is 15.625ms (1 sec / 64). Here is the game running at that rate on three machines, showing the ticks it ran on each of the first twenty frames.

```text
at 30 frames a second    2 2 2 2 2 3 2 2 2 2 2 2 3 2 2 2 2 2 3 2   ->  3840 ticks a minute
at 60 frames a second    1 1 1 1 1 1 1 1 1 1 2 1 1 1 1 1 1 1 1 1   ->  3840 ticks a minute
at 144 frames a second   0 0 1 0 1 0 1 0 1 0 1 0 1 0 1 0 1 0 1 0   ->  3840 ticks a minute
```

Each machine handles a vastly different number of frames in a minute, but the same number of ticks. On the fast machine most frames run no ticks at all, and the game draws the same picture twice because nothing has happened yet. The game is thinking at the same speed on every machine.

## The spiral of death and the accumulator

If the work inside a tick takes longer to do than the tick is long, you finish the frame owing more than you started with, compounding every frame until the game hangs. That is the spiral of death.

The fix is a limit on how many ticks you will run for one frame, throwing the rest away. The game runs slow for a moment instead of freezing. Both parts fall out of some fairly simple maths using a division and a modulo.

```go
steps = min(l.maxSteps, int(accumulated/l.tickDuration))
l.accumulator = accumulated % l.tickDuration
```

The first line is the limit. The second is the useful bit: a modulo is always smaller than its divisor, so whatever is carried over is never more than one whole tick. The debt cannot build up, so the spiral of death is not something I handle, it is something the maths makes impossible.

It was not right first time. I originally kept no running total at all, so I worked out the leftover and then threw it away every frame, and the game quietly lost two ticks in every twelve.

## So why did I use 64?

A tick is one length written down two ways. The catching up counts in whole nanoseconds. The movement code multiplies a speed by a fraction of a second. At 60 that looks like this.

```go
const (
    TicksPerSecond = 60
    TickDuration   = time.Second / TicksPerSecond  // whole nanoseconds
    SecondsPerTick = 1.0 / TicksPerSecond          // fraction of a second
)
```

One sixtieth of a second is 16,666,666.67 nanoseconds. `time.Duration` is an integer, so that truncates to 16,666,666, and multiplying it back up to a second leaves you 40 nanoseconds short.

The fraction fares no better. A computer cannot write a sixtieth exactly, much as you cannot write a third exactly as a decimal. So my two numbers for the same tick disagreed depending on which way you needed to translate it. A guard walking at 120 units a second covered 119.999995 of them.

It is consistent, so the game is still playable and reproducible, and it is far too small to be noticed. You can store both values and use whichever suits the operation, and popular game frameworks do exactly that. It bothered me anyway. I could not see why I should have to keep two numbers for one thing: at best an annoyance, at worst a bug waiting to happen.

## Picking a better number

I thought there must be a tick rate where one number does both jobs, and it turns out there is. If we use a number that can satisfy two rules then it can be used in place of the two variables.

- The tick rate has to divide evenly into a second, so a tick lands on a whole number of nanoseconds.
- The tick rate has to be a power of two. A fraction is stored exactly only when it terminates in binary, and that happens only when its denominator (tick rate in our case) is a power of two.

Using these two rules it turns out that there are only 10 numbers that meet these criteria: 1, 2, 4, 8, 16, 32, 64, 128, 256, 512 (numbers familiar to any programmer or nerd), after that all 'powers of two' no longer divide cleanly into nanoseconds.

Three of those sit inside the range I said would work: 32, 64 and 128.

32 gives a tick of 31.25ms, so a key press can sit waiting up to 31ms before the game even notices it. That is enough to feel, and I would rather not spend it here. 128 halves that again, but it doubles the work for precision a puzzle platformer has no use for. 64 lands in the middle, and has the happy side effect of being the closest of the three to the 60 everyone else uses, so anything I read about timings elsewhere still roughly applies.

So the game runs at 64. A tick is exactly 15,625,000 nanoseconds and exactly 0.015625 seconds, and 64 of them make exactly one second. One number, one conversion, nothing to keep in step.

Is it all win? No. It costs about 7% more thinking per second, which is nothing for my use case. I am not the first person to do this by a long shot. Counter-Strike and Valorant land on powers of two as well, but their reasons are server costs and how quickly a shot registers, so as far as I can tell nobody else picks a rate for the same reason I do (simplification and laziness).

## What is next

The post I promised last time, on testing a game that is supposed to be reproducible. Record what the player did rather than what the world looked like, take a fingerprint of the world once per tick, and run the whole thing with no window at all so the tests go as fast as the machine can manage.
