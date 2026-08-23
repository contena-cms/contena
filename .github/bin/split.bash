#!/usr/bin/env bash

set -euo pipefail

package_name="${1:?package name is required}"
target_ref="${2:?target ref is required}"
ref_type="${3:?ref type is required}"

case "${package_name}" in
    administration) source_dir="Administration" ;;
    core) source_dir="Core" ;;
    elasticsearch) source_dir="Elasticsearch" ;;
    frontend) source_dir="Frontend" ;;
    *) echo "Unsupported package: ${package_name}" >&2; exit 2 ;;
esac

platform_dir="${GITHUB_WORKSPACE:-${PWD}}"
work_dir="${RUNNER_TEMP:-${platform_dir}/.tmp}/contena-split-${package_name}"
bare_dir="${work_dir}/bare"
repo_dir="${work_dir}/repo"
split_branch="split-${package_name}-${GITHUB_SHA:0:12}"
default_branch="trunk"

rm -rf "${work_dir}"
mkdir -p "${work_dir}"

git -C "${platform_dir}" config user.name "Contena Bot"
git -C "${platform_dir}" config user.email "bot@contena.cn"
git -C "${platform_dir}" config --global --add safe.directory "${platform_dir}"

git -C "${platform_dir}" subtree split \
    --prefix="src/${source_dir}" \
    --branch="${split_branch}"

git init --bare --initial-branch="${default_branch}" "${bare_dir}"
git -C "${platform_dir}" push "${bare_dir}" "${split_branch}:${default_branch}"
git clone --branch "${default_branch}" "${bare_dir}" "${repo_dir}"
git -C "${repo_dir}" config user.name "Contena Bot"
git -C "${repo_dir}" config user.email "bot@contena.cn"

if [[ "${ref_type}" == "tag" ]]; then
    git -C "${repo_dir}" tag --force --annotate "${target_ref}" \
        --message="Release ${target_ref}"
    push_ref="refs/tags/${target_ref}:refs/tags/${target_ref}"
else
    git -C "${repo_dir}" checkout --force -B "${target_ref}"
    push_ref="refs/heads/${target_ref}:refs/heads/${target_ref}"
fi

remote_url="https://github.com/contena-cms/${package_name}.git"
git -C "${repo_dir}" remote add contena "${remote_url}"
auth_header="AUTHORIZATION: basic $(printf 'x-access-token:%s' "${TOKEN}" | base64 -w0)"
git -C "${repo_dir}" -c "http.extraheader=${auth_header}" push --force contena "${push_ref}"
