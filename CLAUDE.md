# CLAUDE.md

See [README.md](README.md) and [`docs/tag-matrix.md`](docs/tag-matrix.md) for project overview and architecture.

## Dependencies

- WordPress (core APIs)
- GenerateBlocks plugin (`GenerateBlocks_Register_Dynamic_Tag`, `GenerateBlocks_Dynamic_Tags`, `GenerateBlocks_Dynamic_Tag_Callbacks`)
- Custom fields plugin (ACF or compatible — all calls guarded with `function_exists()`)

## Development

No build pipeline, test suite, or linter. Edit PHP directly, test in WordPress environment.
