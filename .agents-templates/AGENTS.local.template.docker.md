## MANDATORY: Docker Command Execution Override

**CRITICAL: ALL commands MUST be executed inside Docker containers**
**FORBIDDEN: Direct host execution - will cause failures**

### Required Execution Pattern:
```bash
docker exec -it contena_app <command>    # PHP/Composer/Console commands
docker exec -it contena_node <command>   # Node/NPM commands
```

### Examples:
- `docker exec -it contena_app composer cs-fix`
- `docker exec -it contena_app bin/console cache:clear`

**All commands from AGENTS.md must be prefixed with the appropriate docker exec pattern.**

**Container names:** `contena_app` (PHP), `contena_node` (Node) - verify with `docker ps`
