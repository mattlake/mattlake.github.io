# mattlake.codes

Personal site — blog and devlog. Static, built with [Hugo](https://gohugo.io), hosted on GitHub Pages.

## Writing

```bash
hugo new blog/some-post.md      # long-form post
hugo new devlog/some-entry.md   # short dated entry
```

Both are Markdown with YAML front matter. Remove `draft: true` to publish.

```yaml
---
title: "Post title"
date: 2026-08-22
tags: ["dotnet"]          # first tag shows in list rows; each tag gets a page
summary: "One line shown on the home page and in the feed."
---
```

A devlog entry takes an optional `note:` — it renders as the yellow TODO callout.

Code fences take a filename, which becomes the header strip on the block:

````markdown
```csharp {file="Worker.cs"}
```
````

## Running locally

```bash
brew install hugo
hugo server -D          # -D includes drafts
```

## Deploying

Push to `main`. `.github/workflows/deploy.yml` builds with a pinned Hugo and publishes
to Pages. There is no `gh-pages` branch and no build output in the repo.

## Layout

```
content/           markdown — blog/, devlog/, about.md
layouts/           templates; _partials/ chrome, _markup/ code-fence hook
assets/css|js/     stylesheet and the keyboard layer (fingerprinted at build)
static/            fonts, favicon, CNAME
```

## Design

Terminal-flavoured, Catppuccin Mocha, JetBrains Mono + IBM Plex Sans. Vim-style keys:
`g h/b/d/a` to navigate, `j`/`k` and `enter` in lists, `?` for the overlay. All values
come from the design handoff; the palette lives in `:root` in `assets/css/main.css`.

## History

The PHP/Jigsaw version of this site is tagged [`jigsaw-archive`](../../tree/jigsaw-archive).
