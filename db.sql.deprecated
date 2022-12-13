
SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

create table user_code(id SERIAL, moodle_id INTEGER, moodle_user VARCHAR(300), moodle_fullname VARCHAR(2000), code TEXT, event_time TIMESTAMP NOT NULL DEFAULT NOW());


CREATE FUNCTION public.str_normalize(str text) RETURNS text
    LANGUAGE plpgsql
    AS $$
BEGIN
	RETURN TRANSLATE(UPPER(str), 'ÁÀÃÂÉÈÊÍÌÎÓÒÔÕÚÌÛÇÑ', 'AAAAEEEIIIOOOOUUUCN');
END;
$$;

ALTER FUNCTION public.str_normalize(str text) OWNER TO postgres;

COMMENT ON FUNCTION public.str_normalize(str text) IS 'Converte a string informada para maiúsculo e sem acentuação';

CREATE FUNCTION public.trg_pessoas_enderecos_after() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Se estiver inserindo um endereço como principal, considera todos outros da pessoa como não principais
    IF (TG_OP = 'INSERT' AND NEW.principal = TRUE) THEN
		UPDATE pessoas_enderecos
		SET principal = FALSE
		WHERE pessoa = NEW.pessoa
			AND id != NEW.id;
	
	-- Se estiver setando um endereço para principal, considera todos os outros da pessoa como não principais
	ELSIF (TG_OP = 'UPDATE' AND OLD.principal = FALSE AND NEW.principal = TRUE) THEN
		UPDATE pessoas_enderecos
		SET principal = FALSE
		WHERE pessoa = NEW.pessoa
			AND id != NEW.id;
	
	-- Se estiver triando um endereço de principal ou remvendo um endereço princpal, define um outro da pessoal como principal
	ELSIF (
		(TG_OP = 'UPDATE' AND OLD.principal = TRUE AND NEW.principal = FALSE)
		OR (TG_OP = 'DELETE' AND OLD.principal = TRUE)
	) THEN
		UPDATE pessoas_enderecos
		SET principal = TRUE
		WHERE id = (
			SELECT id FROM pessoas_enderecos
			WHERE pessoa = OLD.pessoa
				AND id != OLD.id
			LIMIT 1
		);
    END IF;
    RETURN NEW;
END;
$$;

ALTER FUNCTION public.trg_pessoas_enderecos_after() OWNER TO postgres;

COMMENT ON FUNCTION public.trg_pessoas_enderecos_after() IS 'Ao inserir, alterar ou remover em endereço vai ajustar para ter um principal';

CREATE FUNCTION public.trg_pessoas_telefones_after() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Se estiver inserindo um telefone como principal, considera todos outros da pessoa como não principais
    IF (TG_OP = 'INSERT' AND NEW.principal = TRUE) THEN
		UPDATE pessoas_telefones
		SET principal = FALSE
		WHERE pessoa = NEW.pessoa
			AND id != NEW.id;
	
	-- Se estiver setando um telefone para principal, considera todos os outros da pessoa como não principais
	ELSIF (TG_OP = 'UPDATE' AND OLD.principal = FALSE AND NEW.principal = TRUE) THEN
		UPDATE pessoas_telefones
		SET principal = FALSE
		WHERE pessoa = NEW.pessoa
			AND id != NEW.id;
	
	-- Se estiver tirando um telefone de principal ou removendo um princpal, define um outro da pessoa como principal
	ELSIF (
		(TG_OP = 'UPDATE' AND OLD.principal = TRUE AND NEW.principal = FALSE)
		OR (TG_OP = 'DELETE' AND OLD.principal = TRUE)
	) THEN
		UPDATE pessoas_telefones
		SET principal = TRUE
		WHERE id = (
			SELECT id FROM pessoas_telefones
			WHERE pessoa = OLD.pessoa
				AND id != OLD.id
			LIMIT 1
		);
    END IF;
    RETURN NEW;
END;
$$;

ALTER FUNCTION public.trg_pessoas_telefones_after() OWNER TO postgres;

COMMENT ON FUNCTION public.trg_pessoas_telefones_after() IS 'Ao inserir, alterar ou remover em telefone vai ajustar para ter um principal';

SET default_tablespace = '';

SET default_with_oids = false;

CREATE TABLE public.cidades (
    id integer NOT NULL,
    id_terceiro integer,
    estado integer NOT NULL,
    nome character varying(50) NOT NULL
);

ALTER TABLE public.cidades OWNER TO postgres;

COMMENT ON COLUMN public.cidades.id_terceiro IS 'Identificador do terceiro de onde foi importado (ex: IBGE)';

CREATE SEQUENCE public.cidades_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE public.cidades_id_seq OWNER TO postgres;

ALTER SEQUENCE public.cidades_id_seq OWNED BY public.cidades.id;

CREATE TABLE public.estados (
    id integer NOT NULL,
    id_terceiro integer,
    nome character varying(30) NOT NULL,
    sigla character varying(2) NOT NULL
);

ALTER TABLE public.estados OWNER TO postgres;

COMMENT ON COLUMN public.estados.id_terceiro IS 'Identificador do terceiro de onde foi importado (ex: IBGE)';

CREATE SEQUENCE public.estados_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE public.estados_id_seq OWNER TO postgres;

ALTER SEQUENCE public.estados_id_seq OWNED BY public.estados.id;

CREATE TABLE public.permissoes (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    chave character varying(100) NOT NULL,
    modulo smallint
);

ALTER TABLE public.permissoes OWNER TO postgres;

COMMENT ON TABLE public.permissoes IS 'Pontos de controle de permissão';

COMMENT ON COLUMN public.permissoes.chave IS 'Identificação para checagem nos códigos (API, interface, etc)';

CREATE SEQUENCE public.permissoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE public.permissoes_id_seq OWNER TO postgres;

ALTER SEQUENCE public.permissoes_id_seq OWNED BY public.permissoes.id;

CREATE TABLE public.permissoes_modulos (
    id smallint NOT NULL,
    nome character varying(100),
    icone character varying(30)
);

ALTER TABLE public.permissoes_modulos OWNER TO postgres;

COMMENT ON TABLE public.permissoes_modulos IS 'Organizadores/agrupadores para as permissões';

CREATE SEQUENCE public.permissoes_modulos_id_seq
    AS smallint
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE public.permissoes_modulos_id_seq OWNER TO postgres;

ALTER SEQUENCE public.permissoes_modulos_id_seq OWNED BY public.permissoes_modulos.id;

CREATE TABLE public.pessoas (
    id integer NOT NULL,
    log_data timestamp with time zone DEFAULT now() NOT NULL,
    nome character varying(100) NOT NULL,
    email character varying(100),
    razao_social character varying(100),
    tipo character(1) DEFAULT 'F'::bpchar NOT NULL,
    fornecedor boolean DEFAULT false,
    cliente boolean DEFAULT false,
    fabricante boolean DEFAULT false,
    observacao character varying(500),
    ativa boolean DEFAULT true,
    pessoa integer,
    categoria integer
);

ALTER TABLE public.pessoas OWNER TO postgres;

COMMENT ON TABLE public.pessoas IS 'Todos: pessoas físicas, jurídicas, clientes, fornecedores, usuários, etc';

COMMENT ON COLUMN public.pessoas.log_data IS 'Data de cadastro';

COMMENT ON COLUMN public.pessoas.tipo IS 'F (Física) ou J (Jurídica)';

COMMENT ON COLUMN public.pessoas.fornecedor IS 'Indica para aparecer nas listas de fornecedores';

COMMENT ON COLUMN public.pessoas.cliente IS 'Indica para aparecer nas listas de clientes';

COMMENT ON COLUMN public.pessoas.fabricante IS 'Indica para aparecer nas listas de fabricantes';

COMMENT ON COLUMN public.pessoas.pessoa IS 'Pessoa relacionada (matriz se for PJ ou empresa relacionada se for PF)';

CREATE TABLE public.pessoas_categorias (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    categoria_pai integer
);

ALTER TABLE public.pessoas_categorias OWNER TO postgres;

CREATE SEQUENCE public.pessoas_categorias_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE public.pessoas_categorias_id_seq OWNER TO postgres;

ALTER SEQUENCE public.pessoas_categorias_id_seq OWNED BY public.pessoas_categorias.id;

CREATE TABLE public.pessoas_categorias_permissoes (
    pessoa_categoria integer NOT NULL,
    permissao integer NOT NULL
);

ALTER TABLE public.pessoas_categorias_permissoes OWNER TO postgres;

CREATE TABLE public.pessoas_documentos (
    id integer NOT NULL,
    pessoa integer NOT NULL,
    documento character varying(30) NOT NULL,
    valor character varying(30) NOT NULL
);

ALTER TABLE public.pessoas_documentos OWNER TO postgres;

COMMENT ON COLUMN public.pessoas_documentos.documento IS 'Nome do documento (ex: CNPJ, RG, etc)';

COMMENT ON COLUMN public.pessoas_documentos.valor IS 'Valor (número/codigo) do documento (ex: o CPF em si)';

CREATE SEQUENCE public.pessoas_documentos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE public.pessoas_documentos_id_seq OWNER TO postgres;

ALTER SEQUENCE public.pessoas_documentos_id_seq OWNED BY public.pessoas_documentos.id;

CREATE TABLE public.pessoas_enderecos (
    id integer NOT NULL,
    pessoa integer NOT NULL,
    principal boolean DEFAULT false,
    codigo_postal character varying(10),
    cidade integer NOT NULL,
    bairro character varying(70) NOT NULL,
    logradouro character varying(70) NOT NULL,
    numero character varying(10) NOT NULL,
    complemento character varying(70)
);

ALTER TABLE public.pessoas_enderecos OWNER TO postgres;

COMMENT ON COLUMN public.pessoas_enderecos.principal IS 'Indica que é o endereço principal da pessoa';

CREATE SEQUENCE public.pessoas_enderecos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE public.pessoas_enderecos_id_seq OWNER TO postgres;

ALTER SEQUENCE public.pessoas_enderecos_id_seq OWNED BY public.pessoas_enderecos.id;

CREATE SEQUENCE public.pessoas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE public.pessoas_id_seq OWNER TO postgres;

ALTER SEQUENCE public.pessoas_id_seq OWNED BY public.pessoas.id;

CREATE TABLE public.pessoas_telefones (
    id integer NOT NULL,
    pessoa integer NOT NULL,
    principal boolean DEFAULT false,
    telefone character varying(14) NOT NULL
);

ALTER TABLE public.pessoas_telefones OWNER TO postgres;

COMMENT ON COLUMN public.pessoas_telefones.principal IS 'Indica que é o telefone principal da pessoa';

CREATE SEQUENCE public.pessoas_telefones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE public.pessoas_telefones_id_seq OWNER TO postgres;

ALTER SEQUENCE public.pessoas_telefones_id_seq OWNED BY public.pessoas_telefones.id;

CREATE TABLE public.usuarios (
    id integer NOT NULL,
    login character varying(100) NOT NULL,
    senha character(60) NOT NULL
);

ALTER TABLE public.usuarios OWNER TO postgres;

COMMENT ON TABLE public.usuarios IS 'Extende pessoas, transformando-as em usuários';

COMMENT ON COLUMN public.usuarios.senha IS 'Bcrypt';

ALTER TABLE ONLY public.cidades ALTER COLUMN id SET DEFAULT nextval('public.cidades_id_seq'::regclass);

ALTER TABLE ONLY public.estados ALTER COLUMN id SET DEFAULT nextval('public.estados_id_seq'::regclass);

ALTER TABLE ONLY public.permissoes ALTER COLUMN id SET DEFAULT nextval('public.permissoes_id_seq'::regclass);

ALTER TABLE ONLY public.permissoes_modulos ALTER COLUMN id SET DEFAULT nextval('public.permissoes_modulos_id_seq'::regclass);

ALTER TABLE ONLY public.pessoas ALTER COLUMN id SET DEFAULT nextval('public.pessoas_id_seq'::regclass);

ALTER TABLE ONLY public.pessoas_categorias ALTER COLUMN id SET DEFAULT nextval('public.pessoas_categorias_id_seq'::regclass);

ALTER TABLE ONLY public.pessoas_documentos ALTER COLUMN id SET DEFAULT nextval('public.pessoas_documentos_id_seq'::regclass);

ALTER TABLE ONLY public.pessoas_enderecos ALTER COLUMN id SET DEFAULT nextval('public.pessoas_enderecos_id_seq'::regclass);

ALTER TABLE ONLY public.pessoas_telefones ALTER COLUMN id SET DEFAULT nextval('public.pessoas_telefones_id_seq'::regclass);

ALTER TABLE ONLY public.cidades
    ADD CONSTRAINT cidades_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.estados
    ADD CONSTRAINT estados_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.permissoes_modulos
    ADD CONSTRAINT permissoes_modulos_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.permissoes
    ADD CONSTRAINT permissoes_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.pessoas_categorias_permissoes
    ADD CONSTRAINT pessoas_categorias_permissoes_pkey PRIMARY KEY (pessoa_categoria, permissao);

ALTER TABLE ONLY public.pessoas_categorias
    ADD CONSTRAINT pessoas_categorias_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.pessoas_documentos
    ADD CONSTRAINT pessoas_documentos_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.pessoas_enderecos
    ADD CONSTRAINT pessoas_enderecos_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.pessoas
    ADD CONSTRAINT pessoas_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.pessoas_telefones
    ADD CONSTRAINT pessoas_telefones_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_login_key UNIQUE (login);

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);

CREATE TRIGGER pessoas_enderecos_after AFTER INSERT OR DELETE OR UPDATE ON public.pessoas_enderecos FOR EACH ROW EXECUTE PROCEDURE public.trg_pessoas_enderecos_after();

CREATE TRIGGER pessoas_telefones_after AFTER INSERT OR DELETE OR UPDATE ON public.pessoas_telefones FOR EACH ROW EXECUTE PROCEDURE public.trg_pessoas_telefones_after();

ALTER TABLE ONLY public.cidades
    ADD CONSTRAINT fk_cidades_estados FOREIGN KEY (estado) REFERENCES public.estados(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.permissoes
    ADD CONSTRAINT fk_permissoes_modulos FOREIGN KEY (modulo) REFERENCES public.permissoes_modulos(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.pessoas_categorias
    ADD CONSTRAINT fk_pessoas_categorias_pai FOREIGN KEY (categoria_pai) REFERENCES public.pessoas_categorias(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.pessoas_categorias_permissoes
    ADD CONSTRAINT fk_pessoas_categorias_permissoes_permissoes FOREIGN KEY (permissao) REFERENCES public.permissoes(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.pessoas_categorias_permissoes
    ADD CONSTRAINT fk_pessoas_categorias_permissoes_pessoas_categorias FOREIGN KEY (pessoa_categoria) REFERENCES public.pessoas_categorias(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.pessoas_documentos
    ADD CONSTRAINT fk_pessoas_documentos_pessoas FOREIGN KEY (pessoa) REFERENCES public.pessoas(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.pessoas_enderecos
    ADD CONSTRAINT fk_pessoas_enderecos_cidades FOREIGN KEY (cidade) REFERENCES public.cidades(id) ON DELETE RESTRICT;

ALTER TABLE ONLY public.pessoas_enderecos
    ADD CONSTRAINT fk_pessoas_enderecos_pessoas FOREIGN KEY (pessoa) REFERENCES public.pessoas(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.pessoas
    ADD CONSTRAINT fk_pessoas_pessoas FOREIGN KEY (pessoa) REFERENCES public.pessoas(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.pessoas
    ADD CONSTRAINT fk_pessoas_pessoas_categorias FOREIGN KEY (categoria) REFERENCES public.pessoas_categorias(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.pessoas_telefones
    ADD CONSTRAINT fk_pessoas_telefones_pessoas FOREIGN KEY (pessoa) REFERENCES public.pessoas(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_id_fkey FOREIGN KEY (id) REFERENCES public.pessoas(id) ON DELETE CASCADE;

