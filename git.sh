# For each package
for repo in container database foundation influxdb logger process scheduler server support swoole task websocket; do
  # Remove the existing directory
  rm -rf packages/$repo

  # Add the remote
  git remote add $repo git@github.com:ody-dev/$repo.git

  # Fetch the data
  git fetch $repo

  # Add the subtree
  git subtree add --prefix=packages/$repo $repo master --squash

  # Clean up
  git remote remove $repo
done