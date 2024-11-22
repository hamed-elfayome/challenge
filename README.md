# **Project Overview**

This project is a **Laravel-based system** designed to manage applications, chats, and messages with a robust architecture. It integrates modern technologies like **RabbitMQ**, **Elasticsearch**, **Redis**, and **Swagger** to ensure scalability, reliability, and ease of use.

Access the **Live API Documentation** for real testing via [Swagger Documentation](https://chat.instabug-challenge.online/api/documentation).

You can directly test the live version of the API at:  
`https://chat.instabug-challenge.online/api`, followed by the targeted endpoint.

---

## **Key Features**
1. **Applications Management:**
    - Applications can be created with unique tokens.
    - Each application tracks the number of chats associated with it.

2. **Chats Management:**
    - Chats are created for specific applications, with unique numbers per application.
    - Chat counters are incremented atomically to ensure data consistency.

3. **Messages Management:**
    - Messages are associated with specific chats.
    - Each message has a unique number and body content.
    - Messages are indexed in **Elasticsearch** for efficient search and retrieval.

4. **Technologies and Tools Used:**
    - **Laravel Framework:** Provides the base structure with Eloquent models, routes, and jobs.
    - **RabbitMQ:** Ensures reliable queuing and asynchronous processing for creating applications, chats, and messages.
    - **Redis:**
        - Manages counters for tokens, chats, and messages.
        - Tracks token uniqueness for applications.
    - **Elasticsearch:**
        - Stores messages for advanced search capabilities.
        - Ensures quick and efficient retrieval of message data.
    - **Swagger:** Documents the API endpoints, enabling easy integration and testing on a live environment.
    - **GitHub Actions:** Implements Continuous Deployment (CD) pipelines for automated testing and deployment.

5. **API Design:**
    - Endpoints are versioned under `/v1`.
    - RESTful principles are followed for CRUD operations:
        - **Applications**: `/v1/applications`
        - **Chats**: `/v1/applications/{application_token}/chats`
        - **Messages**: `/v1/applications/{application_token}/chats/{chat_number}/messages`
    - A search endpoint for messages enhances usability.

---

## **Key Implementation Highlights**
### 1. **Job-Driven Architecture**
- **CreateApplicationJob:** Handles application creation with transaction safety and error logging.
- **CreateChatJob:** Manages chat creation, enforces unique constraints, and maintains counters.
- **SendMessageJob:** Creates and indexes messages in Elasticsearch, ensuring the database and search engine stay in sync.

### 2. **Token Generation Service**
- Uses Redis for unique token tracking.
- Combines timestamp, a Redis counter, and random bytes for secure, non-colliding tokens.

### 3. **Database Design**
- Relational structure with three main tables:
    - **Applications:** Tracks `token`, `name`, and `chats_count`.
    - **Chats:** Associates with applications and tracks `messages_count`.
    - **Messages:** Stores individual messages with `number` and `body`.

### 4. **Redis Usage**
- Redis counters ensure atomic operations for IDs.
- Redis sets are used to efficiently manage and trim token tracking.

### 5. **Elasticsearch Integration**
- Messages are indexed post-creation for efficient search capabilities.
- Non-critical failures in Elasticsearch indexing are gracefully handled.

---

## **Documentation**

### **1. API Documentation**
The live Swagger documentation for testing the API can be accessed [here](https://chat.instabug-challenge.online/api/documentation).

### **2. Database Schema**
The database schema can be found in the `database/migrations` folder. Each migration defines the structure for:
- **Applications:** `create_applications_table.php`
- **Chats:** `create_chats_table.php`
- **Messages:** `create_messages_table.php`

### **3. API Controllers**
The API controllers are located in the `app/Http/Controllers/v1` directory:
- **ApplicationController:** Manages application creation and listing.
- **ChatController:** Handles chat creation and retrieval for applications.
- **MessageController:** Manages message creation, retrieval, and search.

### **4. Workers (Jobs)**
The workers are implemented as Laravel Jobs in the `app/Jobs` directory:
- **CreateApplicationJob:** Creates an application with token management.
- **CreateChatJob:** Adds chats to applications and manages counters.
- **SendMessageJob:** Handles message creation and Elasticsearch indexing.

### **5. Tasks**
Tasks (e.g., token generation) are implemented in the `app/Services` directory:
- **TokenService:** Generates unique tokens using Redis and manages the tracking and cleanup of tokens.
