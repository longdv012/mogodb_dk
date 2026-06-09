#!/bin/bash
set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}=== Fixing MongoDB Keyfile Permissions ===${NC}"
docker run --rm -v "$(pwd)/mongo-keyfile":/kf alpine:latest sh -c "chmod 400 /kf && chown 999:999 /kf"
echo -e "${GREEN}Keyfile permissions fixed!${NC}"

echo -e "${BLUE}=== Starting Docker Containers ===${NC}"
docker compose up -d

echo -e "${BLUE}=== Waiting for MongoDB to start ===${NC}"
until docker exec mongo mongosh -u admin -p "longdv!123" --eval "db.adminCommand('ping')" &>/dev/null; do
    printf "."
    sleep 1
done
echo -e "\n${GREEN}MongoDB is up and running!${NC}"

echo -e "${BLUE}=== Ensuring MongoDB Replica Set is Initialized ===${NC}"
docker exec mongo mongosh -u admin -p "longdv!123" --eval '
try {
    var status = rs.status();
    if (status.ok) {
        print("Replica Set (rs0) is already initialized and active.");
    }
} catch (e) {
    print("Replica Set not initialized. Initializing now...");
    var initResult = rs.initiate({
        _id: "rs0",
        members: [{ _id: 0, host: "localhost:27017" }]
    });
    print("rs.initiate() result: " + JSON.stringify(initResult));
}
'

echo -e "${BLUE}=== Ensuring Workspace Permissions are Correct ===${NC}"
USER_ID=${SUDO_UID:-$(id -u)}
GROUP_ID=${SUDO_GID:-$(id -g)}
docker run --rm -v "$(pwd)":/app -w /app alpine:latest chown -R ${USER_ID}:${GROUP_ID} .
# Re-fix keyfile after chown
docker run --rm -v "$(pwd)/mongo-keyfile":/kf alpine:latest sh -c "chmod 400 /kf && chown 999:999 /kf"

echo -e "${BLUE}=== Running Composer Install inside Container ===${NC}"
docker exec laravel_app composer install

echo -e "${BLUE}=== Restarting Horizon Container ===${NC}"
docker compose restart horizon

echo -e "\n${GREEN}====================================================${NC}"
echo -e "${GREEN}🚀 PROJECT STARTED SUCCESSFULLY!${NC}"
echo -e "${GREEN}====================================================${NC}"
echo ""
echo -e "🔗 ${BLUE}Access URLs:${NC}"
echo -e "  - Laravel App:      ${GREEN}http://localhost:8080${NC}"
echo -e "  - Laravel Horizon:  ${GREEN}http://localhost:8080/horizon${NC}"
echo ""
echo -e "🔌 ${BLUE}External Connection Strings:${NC}"
echo -e "  - MongoDB: ${GREEN}mongodb://admin:longdv!123@127.0.0.1:27017/laravel?authSource=admin${NC}"
echo -e "  - Redis:   ${GREEN}redis://127.0.0.1:6379${NC}"
echo ""
echo -e "🧪 ${BLUE}Testing Routes:${NC}"
echo -e "  - Test Batch (success):  ${GREEN}http://localhost:8080/test-batch${NC}"
echo -e "  - Test Batch (fail):     ${GREEN}http://localhost:8080/test-failed-batch${NC}"
echo -e "  - Test Single Job:       ${GREEN}http://localhost:8080/test-single${NC}"
echo ""
echo -e "🧑‍💻 ${BLUE}Monitoring:${NC}"
echo -e "  - Laravel log:       ${GREEN}tail -f storage/logs/laravel.log${NC}"
echo -e "  - Horizon logs:      ${GREEN}docker compose logs -f horizon${NC}"
echo -e "  - Horizon status:    ${GREEN}docker exec laravel_app php artisan horizon:status${NC}"
echo -e "===================================================="
